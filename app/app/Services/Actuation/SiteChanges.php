<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Contracts\CmsAdapter;
use App\Enums\ActuationTier;
use App\Enums\AdapterOutcomeState;
use App\Enums\AutopilotActionType;
use App\Enums\SiteChangeActor;
use App\Enums\SiteChangeUndoState;
use App\Enums\SiteChangeVerdict;
use App\Exceptions\SiteChangeRefused;
use App\Exceptions\TenantMismatch;
use App\Jobs\Actuation\UndoSiteChangeJob;
use App\Models\ActivityFeedItem;
use App\Models\Location;
use App\Models\SiteChange;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one way this platform changes a website it does not own.
 *
 * ⛔ **THE ONLY WRITER OF `site_changes`**, held by a chokepoint lint in
 * `tests/Feature/Architecture/ActuationTest.php`. Not for tidiness: `29` §2 rule
 * 32 — *every site change snapshots its prior state and is reversible* — is
 * enforced **here**, by {@see self::open()} refusing an empty before-snapshot,
 * and a second writer is a second place that refusal can be skipped. The rule is
 * the reason writing to a stranger's site is defensible at all, so it is held by
 * a mechanism rather than remembered (314–316's failure shape, and this is the
 * slice that would otherwise quote it).
 *
 * ## The sequence, and why it has three steps rather than one
 *
 *   1. {@see self::snapshot()} reads the page. **Nothing is recorded.**
 *   2. {@see self::open()} records the intent, with both sides of it. Nothing
 *      has reached the website yet and `applied_at` is null.
 *   3. {@see self::apply()} hands the change set to the adapter, and stamps
 *      `applied_at` **only if the adapter reported writing it**.
 *
 * ⚠️ **STEPS 2 AND 3 ARE SEPARATE BECAUSE SOMEBODY ELSE'S WEBSITE FAILS.** A
 * single call that wrote the row after a successful write would lose every
 * attempt that failed midway — the ones where the page may or may not have
 * changed — which are exactly the ones an owner needs a record of. A single call
 * that wrote it before would report edits that never happened.
 *
 * ## What this slice does not do
 *
 * ⚠️ **NOTHING HERE REACHES A REAL WEBSITE TODAY.** The only bound adapter is
 * {@see LogCmsAdapter} and `BUILD-PLAN` §2.11.3 makes that slice A's deliverable
 * rather than a gap. The live WordPress adapter is slice G and lands *after*
 * measurement and auto-rollback, deliberately: write access without a proven
 * revert is the liability rule 32 names.
 */
final class SiteChanges
{
    public function __construct(private readonly DefaultsRegistry $registry,
        private readonly CmsAdapter $adapter,
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
    ) {}

    /**
     * Read a page's current state for the fields a change set is about.
     *
     * ⚠️ **A PASSTHROUGH ON PURPOSE.** It exists so that the ordinary caller —
     * a publishing job — never needs the adapter in its own constructor, which
     * is what keeps *"open a change set first"* the path of least resistance
     * rather than a rule to remember.
     *
     * @param  list<string>  $fields
     */
    public function snapshot(Location $location, string $url, array $fields): SiteSnapshot
    {
        $this->assertSameTenant($location);

        return $this->adapter->snapshot($location, $url, $fields);
    }

    /**
     * Record what we are about to change, and what it looks like now.
     *
     * ⛔ **REFUSES AN EMPTY `before`. THAT IS RULE 32 AND IT IS THE POINT OF
     * THIS METHOD.** The database can hold `NOT NULL`; only this can hold
     * *meaning*, because `{}` satisfies a NOT NULL column and describes no page.
     *
     * ⛔ **AND A CREATION IS NOT AN EXCEPTION TO IT — THERE IS NO CREATION ARM
     * HERE AND THERE MUST NEVER BE ONE** (5770). Decision 5753 found that a
     * growth page is a page that does not exist yet, and the fix is **not** a
     * bypass in this method: {@see ChangeSet::creating()} arrives carrying
     * {@see ChangeSet::ABSENT_PAGE} — *"no page at this URL when we looked"* —
     * which is a recorded observation and passes the guard by being true. A
     * special case here would be a second route to a change nobody can undo,
     * reachable by anything that could construct the right-looking argument.
     *
     * ⛔ **REFUSES AN UNBUILT TIER RATHER THAN DOWNGRADING** (219). A silent fall
     * back to advisory would make "this site needs a tier we have not built" and
     * "there was nothing to do here" the same outcome.
     *
     * @throws SiteChangeRefused
     */
    public function open(Location $location, ChangeSet $set, ActuationActor $actor): SiteChange
    {
        $this->assertSameTenant($location);

        $set->tier->assertImplemented();

        if ($set->before === []) {
            throw SiteChangeRefused::withoutSnapshot($set->url);
        }

        if ($set->after === []) {
            throw SiteChangeRefused::withoutChange($set->url);
        }

        return SiteChange::create([
            'location_id' => $location->id,
            'url' => $set->url,
            'change_type' => $set->changeType,
            'tier' => $set->tier,
            'before_snapshot' => $set->before,
            'after_snapshot' => $set->after,
            // ⛔ **THE FIELDS THIS CHANGE DELIBERATELY DOES NOT CARRY, AND WHY**
            // (5772). A field the bound adapter cannot write is dropped from the
            // change set rather than refusing the whole publish — and a drop
            // nobody records is indistinguishable from a field nobody asked for,
            // which is 1222's rule. Empty on every change set that withheld
            // nothing.
            'withheld_fields' => $set->withheld,
            'applied_by' => $actor->kind,
            'applied_at' => null,
            'verdict' => SiteChangeVerdict::Pending,
        ]);
    }

    /**
     * Hand the change set to the adapter, and record the result.
     *
     * ⚠️ **THE FEED AND THE AUDIT ROW ARE WRITTEN ONLY ON A SUCCESSFUL WRITE.**
     * `29` §2 rule 42 asks for a feed entry per automated action and an audit row
     * per sensitive one — of the action that *happened*. Telling an owner we
     * improved a page that we could not reach is worse than telling them nothing.
     *
     * ⚠️ **THE FEED CASE IS THE CALLER'S TO NAME, AND `SiteChangeApplied` IS
     * ONLY THE DEFAULT** (5669). Every site change is a change set, so this is
     * the one place a feed entry could be written for all of them — but *"we
     * improved a page on your website"* is a false sentence about a page that
     * did not exist five seconds ago, and slice D's publish is exactly that
     * case. The alternative was for the publisher to file its own
     * `ContentPublished` beside this one, which gives an owner two cards for one
     * act and one of them wrong. **A typed enum rather than a string**, so a
     * caller can pick the wrong sentence but cannot invent one.
     *
     * @throws SiteChangeRefused
     */
    public function apply(
        SiteChange $change,
        ActuationActor $actor,
        AutopilotActionType $feedAction = AutopilotActionType::SiteChangeApplied,
    ): AdapterOutcome {
        if ($change->isApplied()) {
            throw SiteChangeRefused::alreadyApplied((int) $change->id);
        }

        $this->assertChangeBelongsToTenant($change);

        $location = $change->location;

        $this->assertSameTenant($location);

        $set = $this->changeSetOf($change);

        // ⛔ **A CREATION AND AN EDIT ARE TWO VERBS, AND THE ROUTING IS HERE
        // RATHER THAN INSIDE EACH ADAPTER** (5770). An adapter that decided for
        // itself whether `writeChangeSet()` meant *edit this page* or *make this
        // page* is one bad guess away from a duplicate page on a stranger's
        // website — and the guess would be made per implementation, where this
        // service is the one place the answer is already known.
        $outcome = $set->isCreation()
            ? $this->adapter->createPage($location, $set)
            : $this->adapter->writeChangeSet($location, $set);

        if (! $outcome->ok) {
            return $outcome;
        }

        DB::transaction(function () use ($change, $location, $actor, $outcome, $feedAction): void {
            // ⚠️ **THE ADAPTER'S OWN NAME FOR THE PAGE, STORED AND NEVER READ
            // HERE** (5966, 6141). `site_changes.url` is the address we asked
            // for; this is what the adapter says it actually wrote, and it is
            // the only thing that still finds the page after an owner has
            // renamed its slug. **This service does not look inside it** — see
            // {@see AdapterOutcome::$pageRef}.
            $change->forceFill([
                'applied_at' => now(),
                'written_page_ref' => $outcome->pageRef,
            ])->save();

            $this->activity->record(
                $feedAction,
                $location->id,
                $this->feedContext($change),
            );

            $this->audit->record(
                'site_change.applied',
                $actor->auditActor(),
                $change,
                $this->auditContext($change) + ['adapter_detail' => $outcome->detail],
            );
        });

        return $outcome;
    }

    /**
     * Put the page back, and say whose call it was.
     *
     * ⛔ **THE SAME PATH THE AUTOMATIC REVERT AND THE OWNER'S UNDO BOTH TAKE.**
     * `BUILD-PLAN` §2.11.3 slice J: the screen *"adds no second revert
     * mechanism"*. What differs between the two is one enum on the row and the
     * actor in the audit line — **never the code that runs** — because a second
     * revert path is a second place the feed can disagree with the site.
     *
     * ⚠️ **`rolled_back_by` AND THE FEED ENTRY ARE TWO SEPARATE FACTS**, and the
     * tests assert them separately. One is what a later slice quarantines an
     * automation on; the other is what an owner reads.
     *
     * @throws SiteChangeRefused
     */
    public function revert(SiteChange $change, ActuationActor $actor, string $reason): AdapterOutcome
    {
        if (! $change->isApplied()) {
            throw SiteChangeRefused::notApplied((int) $change->id);
        }

        if ($change->rolled_back_at !== null) {
            throw SiteChangeRefused::alreadyRolledBack((int) $change->id);
        }

        if (trim($reason) === '') {
            throw SiteChangeRefused::withoutReason((int) $change->id);
        }

        $this->assertChangeBelongsToTenant($change);

        $location = $change->location;

        $this->assertSameTenant($location);

        // ⛔ **THE PAGE HAS TO BE ON THE WEBSITE THIS LOCATION NAMES, AND THIS
        // IS THE ONLY REVERT PATH THERE IS, WHICH IS EXACTLY WHY THE CHECK
        // BELONGS HERE** (5965, 5976, closed at 6143). 5965 closed both
        // measurement due sets and `ChangeMeasurer` itself; 5974 then closed the
        // speed layer's two callers. **The owner's own Undo was not closed** —
        // `revertById()` came straight through here — so pressing the button on
        // a change set whose page can no longer be identified handed the adapter
        // a URL to resolve against whatever credential the location holds today,
        // and *"putting the page back"* wrote one site's snapshot onto whichever
        // page answers that path on another.
        //
        // ⚠️ **IT IS A RETURN VALUE AND NOT A `SiteChangeRefused`**, unlike the
        // three refusals above it. Those are a caller with the sequence wrong —
        // reverting something that was never applied, or twice. This is an
        // ordinary fact about the world that an owner can cause by changing
        // their own website address, and it arrives at a person who pressed a
        // button: raising here would be a 500 on the account screen.
        //
        // ⛔ **AND IT IS `Unverified` RATHER THAN `Failed`.** Nothing was asked
        // of anybody's website and nothing on one changed; what happened is that
        // we cannot say the page we would write to is the page we changed. See
        // {@see AdapterOutcomeState}.
        if (! SiteMeasurements::addressesTheWebsite($change->url, $location->website_url)) {
            return AdapterOutcome::unverified('the page we changed is not on the website this location names now, so nothing was written');
        }

        $set = $this->changeSetOf($change);

        // ⛔ **UNDOING A CREATION IS AN UNPUBLISH, NEVER A DELETE, AND NEVER AN
        // INVERTED WRITE** (5771). The public state is restored — a visitor sees
        // what they saw before — and nothing on the customer's website is
        // destroyed: the words stay in their own CMS under their own account.
        // {@see ChangeSet::inverted()} refuses a creation rather than swapping a
        // recorded absence into a field map, so this branch cannot be skipped by
        // accident.
        $outcome = $set->isCreation()
            ? $this->adapter->unpublishPage($location, $set)
            : $this->adapter->rollback($location, $set->inverted());

        if (! $outcome->ok) {
            return $outcome;
        }

        DB::transaction(function () use ($change, $location, $actor, $reason, $outcome, $set): void {
            $change->forceFill([
                'rolled_back_at' => now(),
                'rolled_back_by' => $actor->kind,
                'rolled_back_reason' => trim($reason),
                'verdict' => SiteChangeVerdict::RolledBack,
                // ⚠️ **THE REQUEST IS SPENT, WHATEVER ASKED FOR IT** (5835).
                // `rolled_back_at` is what the screen reads first, so leaving
                // this set would change nothing an owner sees — and a column
                // that means *"somebody is waiting on an answer"* left standing
                // beside the answer is the next reader's trap.
                'undo_requested_at' => null,
            ])->save();

            $this->activity->record(
                // ⛔ **THE CASE IS DERIVED FROM THE ROW, NOT SUPPLIED BY THE
                // CALLER — 5807's OWED WORK, CLOSED HERE** (5831). *"Undid a
                // change that was not working"* is true of an edit put back and
                // under-informative about a page that has just left somebody's
                // website; the two are different things to be told. Unlike
                // `apply()` (5669) this takes no parameter, because
                // `isCreation()` already knows and a caller who could choose is
                // a caller who could choose wrong.
                $set->isCreation()
                    ? AutopilotActionType::SitePageTakenDown
                    : AutopilotActionType::SiteChangeReverted,
                $location->id,
                $this->feedContext($change) + ['reason' => trim($reason)],
            );

            $this->audit->record(
                'site_change.reverted',
                $actor->auditActor(),
                $change,
                $this->auditContext($change) + [
                    'rolled_back_by' => $actor->kind->value,
                    'reason' => trim($reason),
                    'adapter_detail' => $outcome->detail,
                ],
            );
        });

        return $outcome;
    }

    /**
     * How long a card may say *"we are undoing this"* before it stops being
     * true.
     *
     * ⛔ **A STATE WITH NO CEILING IS THE DEFECT THIS COLUMN WOULD OTHERWISE
     * INTRODUCE WHILE FIXING ANOTHER** (5835). {@see UndoSiteChangeJob} clears
     * the request on every outcome it reaches, so the only way `undo_requested_at`
     * outlives the attempt is a queue that never ran the job at all — a worker
     * that was down, a flush, a deploy that lost it. **An hour later, *"it takes
     * a few minutes"* is a false sentence**, and the honest thing is to put the
     * card back the way it was so the owner can press again. One hour is
     * comfortably past the job's own retry schedule (five minutes, then thirty).
     *
     * ⛔ **"THE ONLY WAY" NAMED ONE CAUSE AND THERE WERE TWO, AND THE SECOND
     * ONE ENDED HERE RATHER THAN AT A CARD — CORRECTED 2026-08-25 (9580).**
     * *Every outcome it reaches* was doing all the work in that sentence: a
     * **throw** out of {@see self::revertById()} reaches no outcome at all, so
     * the job spent its two attempts, landed in `failed_jobs` — a table whose
     * only alerting reader wants twenty-five rows in an hour — and left the
     * request standing with nothing filed. The card then said *"a few minutes"*
     * for an hour and **fell back to offering Undo with no explanation**, which
     * is the outcome {@see SiteChangeUndoState::UndoDidNotLand} was minted to
     * end, reached through the ceiling that was written to bound a different
     * fault. ⚠️ **The old reading is kept because its argument is unchanged**
     * and because this ceiling is still owed to the cause it names: a job the
     * queue never ran calls no `failed()` either, so **this constant is the only
     * thing standing between that owner and a permanent *"a few minutes"***.
     * What is no longer true is that it is the only thing standing between an
     * owner and silence — {@see UndoSiteChangeJob::failed()} is.
     */
    public const int UNDO_IN_PROGRESS_MINUTES = 60;

    /**
     * Every change we have actually made to this tenant's websites, newest
     * first, in the shape the owner's screen renders — `BUILD-PLAN` §2.11.3
     * slice J.
     *
     * ⛔ **APPLIED ONLY, AND THAT IS 5527's COLUMN DOING ITS JOB** (5832). A
     * change set with a null `applied_at` was opened against a site that then
     * did not answer: nothing reached the page. Listing it on a screen headed
     * *"what we changed on your website"* would report an edit that never
     * happened, which is precisely why `applied_at` exists as a fifth correction
     * to the spec.
     *
     * ⛔ **NOTHING IS EVER HIDDEN OR DROPPED FROM THIS LIST.** An undone change
     * stays, with who undid it and why; a change we can no longer reach stays,
     * saying so. 2075's rule about ratings — *every one is captured and kept* —
     * is the same rule here about our own work, and it is the whole reason this
     * screen is worth an owner's trust.
     *
     * ⚠️ **EVERY LOCATION, NEVER THE SELECTED ONE** (5837). This screen
     * deliberately does not read `LocationContext`: a history filtered by a
     * cursor would hide changes made to a second website behind a control the
     * owner may never have touched, on the one screen whose subject is that
     * nothing is hidden. The card names its location instead.
     *
     * ⚠️ **TENANT-SCOPED BY CONTEXT**, like every other read here: the global
     * scope raises with no tenant established rather than returning nothing, and
     * row-level security stands underneath it.
     *
     * @return list<OwnerSiteChange>
     */
    public function history(ActuationTiers $tiers, ?CarbonImmutable $now = null): array
    {
        Tenancy::idOrFail();

        $now ??= CarbonImmutable::now();

        /** @var Collection<int, SiteChange> $rows */
        $rows = SiteChange::query()
            ->with('location')
            ->whereNotNull('applied_at')
            ->orderByDesc('id')
            ->get();

        /** @var array<int, bool> $reachable */
        $reachable = [];

        $unrevertable = $this->changesARevertCouldNotReach(
            array_values($rows->map(static fn (SiteChange $row): int => (int) $row->id)->all()),
        );

        $cards = [];

        foreach ($rows as $row) {
            $location = $row->location;

            // ⚠️ **ASKED ONCE PER LOCATION RATHER THAN ONCE PER CHANGE.** The
            // answer is a credential row read and a tenant can have a great many
            // changes against one website.
            $reachable[$row->location_id] ??= $tiers->canUndoAt($location, $row->tier);

            $createdThePage = $row->before_snapshot === ChangeSet::ABSENT_PAGE;

            $cards[] = new OwnerSiteChange(
                (int) $row->id,
                $row->location_id,
                $location->name,
                $row->url,
                $row->tier,
                $createdThePage,
                CarbonImmutable::instance($row->applied_at ?? now()),
                $this->undoStateOf(
                    $row,
                    $reachable[$row->location_id],
                    // ⛔ **THE SAME QUESTION THE REVERT ITSELF ASKS, ASKED ON
                    // THE CARD SO NO BUTTON IS DRAWN THAT CANNOT WORK** (5976,
                    // 6143). `SiteMeasurements::addressesTheWebsite()` is the
                    // one spelling of the rule and this is a reader of it, not a
                    // second copy — the guard that matters is in `revert()`,
                    // which is reachable without this screen (398).
                    SiteMeasurements::addressesTheWebsite($row->url, $location->website_url),
                    in_array((int) $row->id, $unrevertable, true),
                    $now,
                ),
                $row->rolled_back_at === null ? null : CarbonImmutable::instance($row->rolled_back_at),
                $row->rolled_back_reason,
                OwnerSiteChange::withheldSentences($row->withheld_fields),
                // ⛔ **THE READER 5819 NAMED AND 5849 LEFT OWED** (6060). Both
                // documents are written on every measured change set and, until
                // this line, nothing anywhere read either back — 272's shape
                // with the owner's own evidence inside it.
                ChangeEvidence::from($row->baseline_metrics, $row->measured_metrics, $createdThePage),
            );
        }

        return $cards;
    }

    /**
     * Which of these change sets had a revert attempted on them that the
     * website did not accept — 5849's owed finding, closed with a **read**.
     *
     * ⛔ **NO SECOND COLUMN, WHICH IS 5849's OWN RULING AND STILL RIGHT.**
     * *"One nullable column per screen state is how a table acquires six of
     * them"* — and the failure already writes a row. {@see UndoSiteChangeJob}
     * files an owner action item naming the change set on every press that does
     * not land, and `ChangeMeasurer::putItBack()` files one for the automatic
     * revert on the same terms. **Reading those back is the whole fix.**
     *
     * ⛔ **KEYED ON `site_change_id` AND NOT ON THE AUTOMATION NAME, BECAUSE THE
     * QUESTION IS ABOUT THE CHANGE AND NOT ABOUT WHO TRIED.** An owner pressing
     * Undo and the nightly measurement putting a page back are two automations
     * with two keys, and the card's sentence is true of both: our change is
     * still on their website and an attempt to take it off did not work.
     * Matching on the automation string as well would be 5817's rename hazard
     * twice over — a lint in `Architecture\ActuationTest` instead enumerates
     * every file that files an owner action item naming a change set, so a third
     * writer has to decide what it means rather than lighting this card up by
     * accident.
     *
     * ⚠️ **THE ROWS THIS READS ARE APPEND-ONLY AND NEVER CLEARED**, so a card
     * that has since been undone successfully would still match — which is why
     * {@see self::undoStateOf()} asks the rollback columns and the live request
     * **first**, and reaches this answer only for a change that is still on the
     * site with nobody waiting on it.
     *
     * @param  list<int>  $changeIds
     * @return list<int>
     */
    private function changesARevertCouldNotReach(array $changeIds): array
    {
        if ($changeIds === []) {
            return [];
        }

        // ⚠️ **TENANT-SCOPED BY THE GLOBAL SCOPE ON `ActivityFeedItem`**, which
        // is what makes reading another tenant's feed impossible here rather
        // than merely unlikely — and the `whereIn` is bounded by this tenant's
        // own change ids besides.
        $items = ActivityFeedItem::query()
            ->where('action_type', AutopilotActionType::OwnerActionNeeded->value)
            ->whereIn(
                DB::raw("metadata->>'site_change_id'"),
                array_map(static fn (int $id): string => (string) $id, $changeIds),
            )
            // ⚠️ **THE ID IS EXTRACTED IN SQL RATHER THAN OUT OF THE CAST
            // ARRAY IN PHP.** `activity_feed.metadata` is a `jsonb` bag with no
            // declared shape — every automation on the platform writes its own
            // keys into it — so reading `$item->metadata['site_change_id']` asks
            // static analysis to believe in a key nothing guarantees. Asking
            // Postgres for the one text value keeps the shape assumption in the
            // predicate, where the `whereIn` above already makes it.
            ->selectRaw("metadata->>'site_change_id' as site_change_id")
            ->get();

        $found = [];

        foreach ($items as $item) {
            $id = $item->getAttribute('site_change_id');

            if (is_string($id) && ctype_digit($id)) {
                $found[] = (int) $id;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Record that the owner has asked, and put the request on the queue.
     *
     * ⛔ **QUEUED BECAUSE A T1 UNDO IS UP TO FOUR REQUESTS TO SOMEBODY ELSE'S
     * WORDPRESS** (5834), at fifteen seconds apiece. A synchronous press would
     * hold a web worker for a minute in the worst case and hand the owner a
     * gateway timeout on a revert that may well have succeeded — which is worse
     * than either honest answer. A T3 undo reaches nobody and does not come
     * through here at all.
     *
     * ⚠️ **THE STAMP AND THE DISPATCH ARE ONE ACT.** The job is dispatched after
     * commit, so it can never read a row that says nobody asked; and the stamp
     * happens first, so a queue that swallows the job leaves a card that says
     * *"undoing"* rather than one that silently did nothing — bounded by
     * {@see $this->registry->int('sites.undo.in_progress_minutes')}.
     *
     * ⚠️ **AUDITED HERE AS WELL AS AT THE REVERT** (`29` §2 rule 42). The revert
     * writes the entry that says a customer's website changed; **this writes the
     * one that says a person asked**, and the two are not the same event — the
     * asking is the only one that happens when the site refuses.
     *
     * @return bool false when there was nothing to ask about
     */
    public function requestUndo(int $changeId, ActuationActor $actor): bool
    {
        $change = SiteChange::query()->find($changeId);

        if (! $change instanceof SiteChange || ! $change->isLive()) {
            return false;
        }

        $this->assertChangeBelongsToTenant($change);

        $this->assertSameTenant($change->location);

        DB::transaction(function () use ($change, $actor): void {
            $change->forceFill(['undo_requested_at' => now()])->save();

            $this->audit->record(
                'site_change.undo_requested',
                $actor->auditActor(),
                $change,
                $this->auditContext($change) + ['requested_by' => $actor->kind->value],
            );
        });

        UndoSiteChangeJob::dispatch(
            Tenancy::idOrFail(),
            $change->location_id,
            (int) $change->id,
            $actor->kind,
            $actor->userId,
        )->afterCommit();

        return true;
    }

    /**
     * Put a page back by id, through the one revert path there is.
     *
     * ⛔ **A LOADER AND NOTHING ELSE.** `BUILD-PLAN` §2.11.3's J row: the screen
     * *"adds no second revert mechanism"*, and the way that sentence is made
     * true rather than asserted is that this method's whole body is a find and a
     * call to {@see self::revert()} — the identical method
     * {@see SiteMeasurements::revert()} hands the automatic rollback to. What
     * differs between an owner undo and an auto-revert is one enum on the row
     * and the actor in the audit line, never the code that runs.
     */
    public function revertById(int $changeId, ActuationActor $actor, string $reason): ?AdapterOutcome
    {
        $change = SiteChange::query()->find($changeId);

        if (! $change instanceof SiteChange) {
            return null;
        }

        return $this->revert($change, $actor, $reason);
    }

    /**
     * What goes in `rolled_back_reason` when a person pressed Undo.
     *
     * ⛔ **ONE STRING FOR BOTH PRESSES.** A T3 undo runs inside the request and
     * a T1 undo runs in {@see UndoSiteChangeJob}; two spellings of this sentence
     * would put two different explanations on the same act depending on which
     * rung the tenant's website is on, and the one an auditor reads is whichever
     * they happened to hit.
     *
     * ⛔ **IT SAYS WHAT HAPPENED, NOT WHO — `rolled_back_by` ALREADY SAYS WHO**
     * (5524), and a reason repeating the actor is a second copy of one fact that
     * can drift from it. The **staff** arm is the exception and is not a
     * repetition: *acting for the account* is a fact about the act rather than
     * about the person, and it is the difference between a customer changing
     * their mind and us doing it for them.
     *
     * ⛔ **THE OWNER IS NEVER ASKED TO TYPE ONE.** A free-text box on an Undo
     * button is a support surface, and it is a place for somebody to write their
     * customer's name into a column this platform keeps for ever.
     */
    public static function ownerUndoReason(ActuationActor $actor): string
    {
        return $actor->kind === SiteChangeActor::Staff
            ? 'Undone from the account\'s own screen by our support team, acting for the account.'
            : 'Undone from the account\'s own screen.';
    }

    /**
     * Take back the *"we are undoing this"* state, because we are not.
     *
     * ⛔ **THE HALF OF THE REQUEST COLUMN THAT COULD HAVE BEEN FORGOTTEN**
     * (5835). A request that outlives its refusal is a card saying *"a few
     * minutes"* for ever about a page that is still exactly where it was —
     * 5759's defect reproduced by the fix for it. The job calls this on every
     * outcome that is not a successful revert.
     */
    public function clearUndoRequest(int $changeId): void
    {
        $change = SiteChange::query()->find($changeId);

        if (! $change instanceof SiteChange) {
            return;
        }

        $change->forceFill(['undo_requested_at' => null])->save();
    }

    /**
     * Is our change still on their website — asked by id, from outside a
     * request.
     *
     * ⛔ **IT EXISTS BECAUSE {@see UndoSiteChangeJob::failed()} MAY NOT ASK THE
     * MODEL ITSELF.** `Architecture\ActuationTest`'s chokepoint permits two
     * files in `app/` to name `SiteChange` at all, and a job reaching for
     * `SiteChange::query()` to answer one question is the second writer rule 32
     * is held by that lint (5521). **A reader on the writer is the chokepoint
     * doing its job rather than an exception to it** — the same argument
     * {@see self::live()} already carries.
     *
     * ⚠️ **AND IT IS THE SAME DEFINITION OF *"LIVE"*, NOT A SECOND COPY.**
     * `SiteChange::isLive()` is applied-and-not-rolled-back, and a job holding
     * its own spelling of that is what serves an owner a sentence about a change
     * they have already undone.
     *
     * ⚠️ **A ROW THAT IS NOT THERE IS NOT LIVE.** A change deleted with its
     * tenant, or one this context cannot see, answers false — so a caller that
     * files an owner action item on a true answer files nothing for a tenant
     * that no longer exists.
     */
    public function isStillLive(int $changeId): bool
    {
        $change = SiteChange::query()->find($changeId);

        return $change instanceof SiteChange && $change->isLive();
    }

    /**
     * Which of {@see SiteChangeUndoState}'s cases this card is.
     *
     * ⛔ **THE ROLLED-BACK ARMS COME OFF `rolled_back_by` AND NOTHING ELSE**
     * (5524). *We* took it off because we measured it doing harm; *you* took it
     * off because you disagreed with us; *our team* took it off for you. Those
     * are three different sentences and collapsing any pair of them tells
     * somebody something false about their own website.
     */
    private function undoStateOf(
        SiteChange $change,
        bool $reachable,
        bool $onTheCurrentWebsite,
        bool $revertDidNotLand,
        CarbonImmutable $now,
    ): SiteChangeUndoState {
        if ($change->rolled_back_at !== null) {
            return match ($change->rolled_back_by) {
                SiteChangeActor::Autopilot => SiteChangeUndoState::UndoneByUs,
                SiteChangeActor::Owner => SiteChangeUndoState::UndoneByYou,
                SiteChangeActor::Staff => SiteChangeUndoState::UndoneByOurTeam,
                // ⛔ A row undone by nobody is unrepresentable — the creating
                // migration's CHECK makes the three rollback columns
                // all-or-nothing (5524) — and reading it as the owner's own
                // decision is the one guess this enum exists to refuse.
                null => SiteChangeUndoState::UndoneByUs,
            };
        }

        $requested = $change->undo_requested_at;

        if ($requested !== null && $now->diffInMinutes(CarbonImmutable::instance($requested), true) < $this->registry->int('sites.undo.in_progress_minutes')) {
            return SiteChangeUndoState::InProgress;
        }

        // ⛔ **ASKED BEFORE `Unreachable`, AND THE ORDER IS THE DECISION**
        // (6143). A location can be both at once — an owner who moved website
        // and disconnected the old one — and only one of the two sentences is
        // true of what we would do next. `Unreachable` ends *"reconnect your
        // website and we will put it back for you"*, which we could not do here:
        // reconnecting is not what is wrong, and `revert()` would refuse again
        // the moment the button was pressed. A promise a reconnect cannot keep
        // is worse than the sentence that says what actually happened.
        if (! $onTheCurrentWebsite) {
            return SiteChangeUndoState::WebsiteChanged;
        }

        if (! $reachable) {
            return SiteChangeUndoState::Unreachable;
        }

        // ⛔ **ASKED LAST, AND THAT ORDER IS THE DECISION** (5849). A change that
        // has since been undone is `Undone`; one with a live request is
        // `Undoing`; one whose website we can no longer reach at all is
        // `Unreachable` and offers no button. **This is the arm underneath all
        // three**: the credential is still ours, nobody is waiting, the change is
        // still on the page, and an attempt to take it off was refused. Before
        // this the card fell through to `Available` — the owner pressed a button,
        // nothing visible happened, and it offered itself again.
        return $revertDidNotLand
            ? SiteChangeUndoState::UndoDidNotLand
            : SiteChangeUndoState::Available;
    }

    /**
     * Every change set of one tier that is on a tenant's site right now.
     *
     * ⚠️ **A READER ON THE WRITER, AND THAT IS THE CHOKEPOINT DOING ITS JOB
     * RATHER THAN AN EXCEPTION TO IT** (5521). The lint permits exactly one file
     * to name `SiteChange`, so a service that needs to *read* the log comes
     * through here too — which is the right answer for a second reason: "live"
     * is applied-and-not-rolled-back, a definition that must not exist in two
     * places, because the second copy is what serves a change an owner has
     * already undone.
     *
     * ⛔ **`before_snapshot` TRAVELS ON THE OBJECT AND MUST NOT LEAVE THE
     * SERVER.** A `ChangeSet` carries both sides by construction; slice I's
     * payload reads `after` only. A caller that serialised one of these whole
     * would publish a verbatim copy of part of the tenant's own page back onto
     * the internet — harmless-looking and wrong.
     *
     * ⚠️ **TENANT-SCOPED BY CONTEXT**, like every other read here: the global
     * scope raises with no tenant established rather than returning nothing, and
     * row-level security stands underneath it.
     *
     * @return list<ChangeSet>
     */
    public function live(ActuationTier $tier): array
    {
        $live = SiteChange::query()
            ->where('tier', $tier)
            ->whereNotNull('applied_at')
            ->whereNull('rolled_back_at')
            ->orderBy('id')
            ->get()
            ->map(fn (SiteChange $change): ChangeSet => $this->changeSetOf($change))
            ->all();

        return array_values($live);
    }

    private function changeSetOf(SiteChange $change): ChangeSet
    {
        return new ChangeSet(
            $change->url,
            $change->change_type,
            $change->tier,
            $change->before_snapshot,
            $change->after_snapshot,
            $change->withheld_fields,
            $change->written_page_ref,
        );
    }

    /**
     * ⚠️ **THE OWNER'S VIEW CARRIES NO PAGE CONTENT.** `ActivityService`'s
     * metadata is broadcast to any open staff screen, and a snapshot read off a
     * live site can contain whatever was on that page. The change set itself is
     * on the row this entry names.
     *
     * @return array<string, mixed>
     */
    private function feedContext(SiteChange $change): array
    {
        return [
            'site_change_id' => (int) $change->id,
            'url' => $change->url,
            'change_type' => $change->change_type,
            'tier' => $change->tier->value,
        ];
    }

    /**
     * ⚠️ **THE FIELD NAMES, NEVER THE VALUES, AND `AuditService`'s DOCBLOCK ASKS
     * FOR BOTH SIDES — SO THIS IS A DELIBERATE DEPARTURE.** Its rule is that an
     * audit entry recording only the new value cannot answer what happened.
     * Here it can: the entry names the `SiteChange` row, and that row holds both
     * snapshots in full and is the durable record of them. Copying a stranger's
     * page content into a second store buys no new answer and stores more of
     * other people's data, which is `CLAUDE.md`'s second tie-breaker.
     *
     * @return array<string, mixed>
     */
    private function auditContext(SiteChange $change): array
    {
        return [
            'site_change_id' => (int) $change->id,
            'url' => $change->url,
            'change_type' => $change->change_type,
            'tier' => $change->tier->value,
            'before_fields' => array_keys($change->before_snapshot),
            'after_fields' => array_keys($change->after_snapshot),
            // ⚠️ **WHETHER THIS BROUGHT A PAGE INTO EXISTENCE**, which is the
            // difference between an edit an owner can compare and a page that
            // was not there at all — and it is what tells a reader of this
            // entry that the undo was an unpublish rather than a restore.
            'created_page' => $this->changeSetOf($change)->isCreation(),
            // ⛔ **THE FIELDS WE COULD NOT WRITE AND THE REASON, ON THE
            // APPEND-ONLY RECORD** (5772). Field names and fixed adapter reasons
            // only — never a value, on this method's own rule.
            'withheld_fields' => $change->withheld_fields,
        ];
    }

    /**
     * ⚠️ **`TenantMismatch`'s OWN ARGUMENT, AT A SECOND ADDRESS.** A loaded
     * `Location` is self-scoping; an *unsaved* one is not loaded at all, and if
     * this service took its `business_id` as authorization the argument would
     * have become the boundary. The tenant comes from context and the two are
     * asserted to agree.
     */
    private function assertSameTenant(Location $location): void
    {
        $tenant = Tenancy::idOrFail();

        if ($location->business_id !== $tenant) {
            throw new TenantMismatch($tenant, $location->business_id);
        }
    }

    /**
     * The same assertion, made against the row rather than against its
     * location.
     *
     * ⛔ **WITHOUT THIS, THE GUARD IN {@see self::apply()} AND {@see self::revert()}
     * COULD NEVER FIRE — FOUND BY DRIVING IT** (5840). Both methods read
     * `$change->location` and hand it to {@see self::assertSameTenant()}, and a
     * location belonging to another tenant is **invisible to the relation**:
     * the global scope and row-level security both filter it, so the property
     * resolves to `null` and the typed parameter raises a `TypeError` before the
     * tenancy check is reached. The refusal was correct and its *name* was a
     * crash — 398's shape one layer down, where an outer guard makes the inner
     * one unreachable rather than merely redundant.
     *
     * ⚠️ **IT ASKS THE ROW BECAUSE THE ROW IS WHAT CARRIES THE ANSWER.**
     * `site_changes.business_id` is on the record in hand and needs no join, so
     * it is readable whatever the relation does — and it is the tenant that
     * actually matters, since it is the row this call is about to rewrite.
     */
    private function assertChangeBelongsToTenant(SiteChange $change): void
    {
        $tenant = Tenancy::idOrFail();

        if ($change->business_id !== $tenant) {
            throw new TenantMismatch($tenant, $change->business_id);
        }
    }
}
