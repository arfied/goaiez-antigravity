<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\AutopilotActionType;
use App\Enums\SiteChangeVerdict;
use App\Enums\SiteSnapshotState;
use App\Enums\SpeedFix;
use App\Models\SiteChange;
use App\Services\ActivityService;
use App\Services\Config\DefaultsRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The measurement half of `site_changes` — the second and last file in `app/`
 * permitted to name that model.
 *
 * ⛔ **IT EXISTS BECAUSE THE THREE MEASUREMENT COLUMNS NEEDED A WRITER AND
 * `SiteChanges` COULD NOT BE THE ONE** (5525). `BUILD-PLAN` §2.11.3 slice A made
 * that class the only writer for one reason and one reason only: `29` §2 rule 32
 * is enforced by `open()` refusing an empty `before_snapshot`, and a second
 * *creator* is a second route past it. **This class cannot create a change set
 * and cannot delete one** — a lint asserts that separately from the allowlist
 * entry, so widening the door by one file did not widen the rule it protects
 * (511's shape avoided by narrowing rather than by trust).
 *
 * ⛔ **AND IT DOES NOT REVERT ANYTHING ITSELF.** {@see self::revert()} loads the
 * row and hands it to {@see SiteChanges::revert()}, which is the same path slice
 * J's Undo screen takes. §2.11.3's J row is explicit that the screen *"adds no
 * second revert mechanism"*, and the same sentence has to be true of the
 * automatic one — what differs between an owner undo and an auto-revert is one
 * enum on the row and the actor in the audit line, never the code that runs.
 *
 * ## Windows anchored on `applied_at`, and what that buys
 *
 * ⚠️ **EVERY WINDOW IS DERIVED FROM `applied_at` AND NEVER FROM THE CLOCK**, so
 * a measurement deferred by a week — a paused tenant, a Search Console window
 * Google has not finished counting, a queue that was down — produces **the same
 * verdict from the same rows** when it eventually runs. That is what makes the
 * sweep safe to re-run and the job safe to retry, and it is the reason nothing
 * here needs a claim or a lock.
 */
final class SiteMeasurements
{
    /**
     * How long after the write the measured window closes — `29` §2 rule 32's
     * *"measure 14–30 days"*, upper bound.
     */
    public const int MEASURED_WINDOW_ENDS_DAYS = 30;

    /**
     * When the measured window opens.
     *
     * ⚠️ **THE FIRST FORTNIGHT IS DELIBERATELY NOT MEASURED.** A page published
     * on day zero has not been crawled, indexed or ranked, and a change to an
     * existing page has not propagated; counting that fortnight would report the
     * indexing delay as the change's effect and revert every page we ever wrote.
     */
    public const int MEASURED_WINDOW_STARTS_DAYS = 14;

    /** How much history the change is judged against. Rule 32's *"14 days pre-change"*. */
    public const int BASELINE_DAYS = 14;

    /**
     * How many change sets one sweep of one tenant may hand out.
     *
     * ⛔ **`GrowthPages::dueForRelease()` HAS TAKEN A LIMIT SINCE THE DAY IT WAS
     * WRITTEN AND ITS THREE SIBLINGS TOOK NONE** (6055). A sweep with no ceiling
     * loads every matching row of every tenant into memory and dispatches a job
     * for each — and the population it selects from is one 6156 says grows,
     * because a change set nobody can act on now stays in the due set for ever.
     * **200 is `dueForRelease()`'s own figure**, kept rather than re-derived so
     * that four sweeps over the same shape of work do not disagree about what a
     * batch is.
     *
     * ⚠️ **THE REMAINDER IS LEFT, NEVER DROPPED.** Every due set is ordered by
     * `id` and every row it does not return is still due on the next run, so the
     * limit spreads the work over nights rather than discarding any of it.
     */
    public const int SWEEP_LIMIT = 200;

    /**
     * How many pages of `SWEEP_LIMIT` rows one sweep will read through looking
     * for rows it can act on.
     *
     * ⛔ **A PLAIN `LIMIT` WOULD HAVE A HEAD-OF-LINE FAILURE AND 6156 PREDICTS
     * ITS POPULATION.** {@see self::identifiable()} drops any change set whose
     * page is no longer on its location's website, and those rows are
     * **permanently** undroppable from the query because nothing marks them. So
     * a tenant with 200 such rows at low ids would have every sweep return an
     * empty batch, for ever, with the build green — a limit that silently
     * switches the sweep off. Reading up to five pages walks past them while
     * still bounding the work.
     *
     * ⚠️ **IT IS A BOUND AND NOT A CURE.** A tenant with more than
     * `SWEEP_LIMIT * SWEEP_SCAN_PAGES` unidentifiable rows ahead of an actionable
     * one is still starved, and the honest fix is to mark them — which is 5970's
     * owed screen state and not this slice's.
     */
    public const int SWEEP_SCAN_PAGES = 5;

    /**
     * How many failed automatic revert attempts one change set gets.
     *
     * ⛔ **THE RETRY IS RIGHT AND THE ABSENCE OF AN END WAS NOT** (6055).
     * {@see self::dueForRevert()} exists because leaving our own harmful edit on
     * a customer's website is worse than asking again — and it re-asked every
     * night for ever, with no counter and nothing that ever said we had given
     * up. **Ten attempts spread over {@see self::REVERT_BACKOFF_HOURS} is about
     * three weeks**, which is the same order as rule 32's own measurement window
     * and long enough to outlast a hosting migration, an expired certificate or
     * a fortnight's holiday.
     *
     * ⚠️ **REACHING IT IS NOT SILENT.** {@see self::noteRevertCeilingReached()}
     * files an owner action item carrying the change-set id, which is the fact
     * `SiteChanges::history()` already reads to light the card that says the
     * change is still on their website. A ceiling nobody is told about is 272's
     * shape with a customer's page attached.
     */
    public const int REVERT_ATTEMPT_CEILING = 10;

    /**
     * Hours to wait after the nth failed attempt before the next one.
     *
     * ⛔ **THE FIRST TWO ARE ZERO, AND THAT IS THE ENTRY WORTH READING TWICE.**
     * The sweep runs nightly, so *the sweep is already the spacing* for the
     * early attempts: a `0` here means *"no wait beyond the next sweep"*, which
     * is exactly the behaviour `SiteMeasurements::dueForRevert()` and
     * `SpeedFixes::dueForJudgement()` have always had and which four tests
     * describe as *"and is tried again"*. **Adding a wait to the first failure
     * would change what those sentences mean to save nothing** — a one-hour
     * backoff under a nightly sweep is a night either way. What the ladder buys
     * is the far end: attempt ten lands about three and a half weeks after
     * attempt one instead of ten nights after it.
     *
     * ⚠️ **THE LAST ENTRY IS REUSED FOR EVERY ATTEMPT BEYOND THE LIST**, so a
     * schedule shorter than the ceiling cannot fall off its own end.
     *
     * ⛔ **NO JITTER HERE, AND THAT IS DELIBERATE RATHER THAN FORGOTTEN.**
     * Jitter exists to break up synchronised retries by many clients against one
     * server. These attempts are already serialised by a nightly sweep and
     * spaced in days; what genuinely needs jitter is the queue's own retry of a
     * thrown failure, which is `MeasureSiteChangeJob::backoff()` and has it.
     *
     * @var list<int>
     */
    public const array REVERT_BACKOFF_HOURS = [0, 0, 24, 24, 48, 48, 96, 168];

    public function __construct(private readonly DefaultsRegistry $registry,
        private readonly SiteChanges $siteChanges,
        private readonly ActivityService $activity,
    ) {}

    public function revertBackoffHours(): array
    {
        return $this->registry->intList('sites.revert.backoff_hours');
    }

    /**
     * Every applied, unmeasured, still-live change whose window has closed.
     *
     * ⛔ **A ROLLED-BACK CHANGE IS NEVER MEASURED, AND THE REASON IS 5524.** An
     * owner pressing Undo is a customer telling us we were wrong; measuring it
     * afterwards and quarantining the automation on the result would report
     * their decision as our self-correction, which is exactly the collapse
     * `rolled_back_by` exists to prevent.
     *
     * ⛔ **AND A SPEED FIX IS NEVER MEASURED HERE, BECAUSE THIS IS THE WRONG
     * QUESTION TO ASK ABOUT ONE** (5860). `28` §4.3 judges a speed change over
     * *"7 days vs the 14-day pre-change baseline"* on p75 LCP/INP, CLS, the
     * conversion rate and the JavaScript error rate — and
     * `App\Services\Actuation\SpeedDecider` has already done so, three weeks
     * before this sweep's window closes. Left alone, this would then judge the
     * same row a second time on page visits and Google clicks and revert a
     * healthy speed fix on a search wobble: a fifth trigger nobody specified,
     * arriving on somebody else's website. **One `site_changes` row, one
     * measurer**, and `SpeedFix::changeTypes()` is the seam.
     *
     * ⛔ **AND THAT CLAUSE HELD ONLY FOR CALLERS THAT CAME THROUGH HERE, WHICH
     * IS THE HALF 5860 DID NOT CLOSE — CORRECTED 2026-08-20 (5962).**
     * {@see ChangeMeasurer::measure()} is public and takes an id, and handed a
     * speed fix's change set directly it measured it on page visits and Google
     * clicks and reverted it. **Which measurer owns a row is now a property of
     * the row** — {@see MeasurableChange::isTheSpeedLayers()}, off
     * {@see SpeedFix::owns()} — and this predicate is the sweep's half of one
     * rule rather than the whole of it.
     *
     * ⚠️ **`dueForRevert()` BELOW IS STILL NOT NARROWED BY CHANGE TYPE.** It
     * carries the obligation to retry a revert that did not land, which is
     * generic — it re-asks the adapter and re-reads the quarantine's own
     * recorded reason rather than re-deriving a verdict.
     * ⛔ **BUT IT *IS* NARROWED BY ADDRESS, AND THE TWO NARROWINGS ARE DIFFERENT
     * QUESTIONS** (5968): 5860 is about which measurer owns a row, and a retry
     * is generic; {@see self::identifiable()} is about which page a write would
     * land on, and a retry that cannot say is exactly as dangerous as a first
     * attempt that cannot.
     *
     * @return list<int>
     */
    public function dueForMeasurement(CarbonImmutable $now, int $limit = self::SWEEP_LIMIT): array
    {
        return $this->identifiableUpTo(
            SiteChange::query()
                ->with('location')
                ->whereNotNull('applied_at')
                ->whereNull('measured_at')
                ->whereNull('rolled_back_at')
                ->whereNotIn('change_type', SpeedFix::changeTypes())
                ->where('applied_at', '<=', $now->subDays($this->registry->int('sites.measure.window_ends_days'))),
            $limit,
        );
    }

    /**
     * Every change already measured as a regression that is still on the site.
     *
     * ⛔ **THIS IS THE RETRY, AND WITHOUT IT A FAILED REVERT LEAVES OUR OWN
     * HARMFUL CHANGE ON A CUSTOMER'S WEBSITE FOR EVER.** The measurement is
     * recorded before the adapter is asked to put the page back, because the
     * evidence for the verdict must survive a site that did not answer — and
     * that is precisely the row {@see self::dueForMeasurement()} can no longer
     * see. `gbp:revoke-owed-grants` retrying nightly is the same shape (4884):
     * the obligation outlives the attempt, so something has to carry it.
     *
     * ⛔ **IT CANNOT CARRY A SPEED FIX AND NEVER COULD** (5861). This selects on
     * `site_changes.verdict = regressed` and {@see SpeedDecider} writes its
     * verdict to `speed_change_sets`; {@see SpeedFixes::dueForJudgement()} is
     * that layer's own due set. The claim that this method covered those rows
     * was written before it was checked, and an assertion found it.
     *
     * ⛔ **AND IT REFUSES A ROW WHOSE PAGE IT CANNOT NAME** (5965), on
     * {@see self::identifiable()}'s reasoning: re-asking a site to put back a
     * page at an address that is no longer on its website writes a page we never
     * touched.
     *
     * @return list<int>
     */
    public function dueForRevert(?CarbonImmutable $now = null, int $limit = self::SWEEP_LIMIT): array
    {
        // ⚠️ **THE CLOCK IS OPTIONAL HERE AND REQUIRED ON ITS SIBLING, AND THAT
        // ASYMMETRY IS ARGUED** (6265). `dueForMeasurement()` *derives* a window
        // from the clock, so a caller that did not supply one would be asking a
        // different question; this only compares against a stored backoff, and
        // the sweep passes its own single `now()` so that both halves of one run
        // agree about when it ran.
        $now ??= CarbonImmutable::now();

        return $this->identifiableUpTo(
            SiteChange::query()
                ->with('location')
                ->whereNotNull('applied_at')
                ->whereNotNull('measured_at')
                ->whereNull('rolled_back_at')
                ->where('verdict', SiteChangeVerdict::Regressed)
                // ⛔ **6265's TWO PREDICATES, AND THEY ARE THE WHOLE OF THE
                // BOUND.** The first stops a row coming back after its last
                // attempt; the second is the backoff, and without it a ceiling
                // is the same nightly hammering with an end date.
                ->whereNull('revert_attempts_exhausted_at')
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('revert_attempt_after')
                    ->orWhere('revert_attempt_after', '<=', $now)),
            $limit,
        );
    }

    /**
     * One change, in the shape the measurer needs.
     */
    public function subject(int $changeId): ?MeasurableChange
    {
        $change = SiteChange::query()->find($changeId);

        if (! $change instanceof SiteChange || $change->applied_at === null) {
            return null;
        }

        return new MeasurableChange(
            (int) $change->id,
            $change->location_id,
            $change->url,
            $change->change_type,
            CarbonImmutable::instance($change->applied_at),
            $change->verdict,
            $change->measured_at !== null,
            self::describesNoPriorPage($change->before_snapshot),
            $change->rolled_back_at !== null,
        );
    }

    /**
     * Is this change still on the site?
     *
     * ⛔ **THE ONE DEFINITION OF *LIVE*, ASKED THROUGH THE MODEL THAT HOLDS IT.**
     * {@see SiteChanges::live()}'s docblock is explicit that applied-and-not-
     * rolled-back must not exist in two places, because the second copy is what
     * serves a change an owner has already undone. This is the speed layer's
     * route to that answer, and the chokepoint is why it is a method here rather
     * than a `SiteChange` query in {@see SpeedDecider}.
     */
    public function isLive(int $changeId): bool
    {
        $change = SiteChange::query()->find($changeId);

        return $change instanceof SiteChange && $change->isLive();
    }

    /**
     * Every one of these change sets whose recorded URL is still a page on the
     * website its location names.
     *
     * ⛔ **5819(a)'s HALF THAT CAN BE ANSWERED WITHOUT ASKING THE SITE** (5965).
     * `Publishing` and {@see SpeedFixes} both build a change set's URL from
     * `locations.website_url`, so the two agree the moment the row is written —
     * and `App\Services\Tenant\LocationWebsite::confirm()` is a supported writer
     * that moves the column afterwards. When they disagree, the page we would
     * measure and the page we would write are two different pages: the mart is
     * keyed on a **path** with no host in it, and the adapter resolves that path
     * against whatever credential the location holds today.
     *
     * ⛔ **SO THE ROW LEAVES BOTH DUE SETS RATHER THAN BEING JUDGED.** Leaving it
     * in would put a job on the queue every night for ever about a change nobody
     * can act on, which is 5746's card-a-night failure wearing a worker.
     *
     * ⚠️ **AND IT IS NOT SILENT, WHICH IS THE QUESTION 1222 ASKS OF ANY REFUSAL
     * NOBODY IS TOLD ABOUT.** {@see SiteChanges::history()} lists **every**
     * applied change set and hides none, so the change is on the owner's screen
     * saying it is still on their website — which is true. ⛔ **What it does not
     * yet say is why we can no longer act on it**, and that screen state is owed
     * (5970). It is deliberately not a log line per row per night: a replaced
     * website is a permanent state rather than a site that might answer
     * tomorrow, so `noteUnrevertable()`'s nightly warning is the wrong
     * precedent here.
     *
     * ⚠️ **AND `dueForRevert()` IS NARROWED HERE WHERE 5860 DELIBERATELY DID NOT
     * NARROW IT.** That decision is about *which measurer owns a row*, and the
     * retry is generic. This is about *which page a write would land on*, and a
     * retry that cannot say is exactly as dangerous as a first attempt that
     * cannot.
     *
     * @param  Collection<int, SiteChange>  $changes
     * @return list<int>
     */
    private static function identifiable(Collection $changes): array
    {
        $ids = [];

        foreach ($changes as $change) {
            if (self::addressesTheWebsite($change->url, $change->location->website_url)) {
                $ids[] = (int) $change->id;
            }
        }

        return $ids;
    }

    /**
     * The same question asked by id, for a caller that holds a change-set id and
     * no location.
     *
     * ⛔ **`SpeedDecider` REVERTS THROUGH {@see self::revert()} AND HAD NO
     * ADDRESS CHECK OF ITS OWN, WHICH IS THIS SLICE'S FIX WAVE REPRODUCING THE
     * DEFECT IT WAS FIXING** (363–365, and this review found it). A speed fix in
     * flight when an owner replaces their website is judged by `28` §4.3 a week
     * later and put back — at a path resolved against whatever site the location
     * is connected to now. **Guarding one of two revert callers is not guarding
     * the revert.**
     */
    public function pageIsIdentifiable(int $changeId): bool
    {
        $change = SiteChange::query()->with('location')->find($changeId);

        return $change instanceof SiteChange
            && self::addressesTheWebsite($change->url, $change->location->website_url);
    }

    /**
     * Whether a recorded change-set URL is a page on this website.
     *
     * ⛔ **THE ONE SPELLING OF THE RULE**, read by both due sets, by
     * {@see self::pageIsIdentifiable()} and by {@see ChangeMeasurer::measure()}'s
     * own refusal — 398's lesson stated forwards: the sweep filtering is not a
     * reason for the service to have no check, because the service is callable
     * without the sweep.
     *
     * ⚠️ **A PREFIX MATCH RATHER THAN A HOST COMPARISON, BECAUSE THE URL WAS
     * BUILT BY CONCATENATION.** `LocationWebsite::normalise()` keeps a path — a
     * business can genuinely live at a page on a larger site (1083's franchisor
     * case) — so a host-only test would call two tenants on one domain the same
     * website. **The trailing separator is required** so that
     * `https://ex.test` is not read as a prefix of `https://ex.testing.com`.
     *
     * ⚠️ **AND A LOCATION WITH NO WEBSITE NAMES NO PAGE.** Fail closed: a null
     * here is *"we cannot say"*, and the answer to that is never a write.
     */
    public static function addressesTheWebsite(string $url, ?string $websiteUrl): bool
    {
        if ($websiteUrl === null || $websiteUrl === '') {
            return false;
        }

        $root = rtrim($websiteUrl, '/');

        return $url === $root || str_starts_with($url, $root.'/');
    }

    /**
     * Did this change set's `before` describe **no page at all**?
     *
     * ✅ **THE SEAM 5804 BUILT, AND IT COST THE ONE EDIT IT PROMISED** (5820).
     * This was a provisional predicate — *every value in the document is
     * empty* — written while the adapter contract's absent marker was on a
     * branch this lane could not see, with the reason stated and the whole
     * question routed through this one method so adopting the real answer would
     * be a single change. The real answer is {@see ChangeSet::ABSENT_PAGE},
     * ruled at 5770, and this is now a comparison against it.
     *
     * ⛔ **IT COMPARES AGAINST THE CONSTANT RATHER THAN RE-STATING THE RULE.**
     * `ChangeSet::isCreation()` is the definition, `SiteChanges::apply()` routes
     * `createPage()` on it and `revert()` routes `unpublishPage()` on it — so a
     * second spelling here is a second idea of what a creation is, in the one
     * place that decides whether a page's baseline is withheld.
     *
     * ⛔ **ABSENT AND UNREAD ARE NOT THE SAME AND MUST NOT BECOME SO** (5770's
     * own hazard). An unread snapshot never reaches this table at all —
     * {@see SiteChanges::open()} refuses an empty `before` and
     * {@see SiteSnapshotState::permitsAChangeSet()} refuses `Unread` and
     * `Unreadable` above it — so every document this method sees is either
     * content or a recorded absence.
     *
     * ⚠️ **AND IT STILL FAILS SOFT.** Read a creation as an edit and the
     * baseline records `0` pageviews instead of `null`; the page signal is
     * `InsufficientData` either way, because zero is below any floor. The cost
     * of being wrong is a less honest evidence document, never a different
     * verdict and never a revert that should not have happened.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private static function describesNoPriorPage(array $snapshot): bool
    {
        return $snapshot === ChangeSet::ABSENT_PAGE;
    }

    /**
     * Write the evidence and the finding.
     *
     * ⛔ **THE EVIDENCE IS WRITTEN BEFORE THE PAGE IS PUT BACK, NEVER AFTER.**
     * A revert can fail — somebody else's website — and a row that had been
     * reverted with no record of why would be an automatic edit to a customer's
     * site that this platform could not explain. The verdict written here is
     * overwritten by {@see SiteChanges::revert()} with `rolled_back`, which is
     * the honest end state: `rolled_back_by` and `rolled_back_reason` are what
     * then say whose call it was and on what (5524).
     */
    public function record(
        int $changeId,
        ChangeMetrics $baseline,
        ChangeMetrics $measured,
        SiteChangeVerdict $verdict,
    ): void {
        $change = SiteChange::query()->find($changeId);

        if (! $change instanceof SiteChange) {
            return;
        }

        // forceFill because the columns are guarded on the model: this service
        // is their only writer and a request body must never be one.
        $change->forceFill([
            'baseline_metrics' => $baseline->toArray(),
            'measured_metrics' => $measured->toArray(),
            'measured_at' => now(),
            'verdict' => $verdict,
        ])->save();
    }

    /**
     * Put the page back, through the one revert path there is.
     */
    public function revert(int $changeId, ActuationActor $actor, string $reason): ?AdapterOutcome
    {
        $change = SiteChange::query()->find($changeId);

        if (! $change instanceof SiteChange) {
            return null;
        }

        // ⚠️ **`SiteChanges::revert()` RETURNS AN `AdapterOutcome` AND NEVER
        // NULL.** The `?AdapterOutcome` on this method's own signature is the
        // *"there is no such change set"* arm above, which is a different fact.
        $outcome = $this->siteChanges->revert($change, $actor, $reason);

        if (! $outcome->ok) {
            $this->countFailedRevert($change);
        }

        return $outcome;
    }

    /**
     * Say that the speed layer has stopped retrying a revert too.
     *
     * ⛔ **ONE FILE FILES THIS FACT, AND THE REASON IS A LINT RATHER THAN
     * TIDINESS** (6266). `ActuationTest`'s *"every file that files an owner
     * action item naming a change set is one the undo card reads"* asserts the
     * set of writers **equal** to an enumerated list, so every new one is an
     * argument somebody has to make. {@see SpeedFixes} carries its own attempt
     * counter on its own table — 5861: this sweep cannot see those rows — and
     * asks here for the owner-facing half, so the enumeration grows by one
     * rather than by two and the sentence an owner reads is written once.
     */
    public function noteRevertCeilingReached(int $changeId): void
    {
        $change = SiteChange::query()->find($changeId);

        if ($change instanceof SiteChange) {
            $this->tellTheOwnerWeHaveStopped($change);
        }
    }

    /**
     * One more failed automatic attempt on a row this sweep can see.
     *
     * ⛔ **THE OUTCOME IS OBSERVED HERE RATHER THAN COUNTED AT HAND-OUT, AND THE
     * DIFFERENCE IS WHETHER THE NUMBER IS TRUE** (6265). Incrementing inside
     * {@see self::dueForRevert()} would count attempts *asked for*: a queue that
     * was down for a week would exhaust a change set's ceiling without one
     * request ever reaching the customer's website, and the owner would be told
     * we had given up on something we never tried. This runs on the way back
     * from the adapter, so it counts attempts that happened.
     *
     * ⛔ **AND IT IS NARROWED TO EXACTLY {@see self::dueForRevert()}'s OWN
     * PREDICATE.** {@see SpeedDecider} reverts through the same method, and its
     * verdict lives on `speed_change_sets` rather than here (5861) — so a speed
     * fix's `site_changes` row can never enter this due set, and a counter
     * written on it would be a column with no reader: 272's shape, arriving
     * inside the fix for 272's shape.
     */
    private function countFailedRevert(SiteChange $change): void
    {
        if ($change->verdict !== SiteChangeVerdict::Regressed || $change->revert_attempts_exhausted_at !== null) {
            return;
        }

        $attempts = (int) $change->revert_attempts + 1;
        $exhausted = $attempts >= $this->registry->int('sites.revert.attempt_ceiling');

        // forceFill because the columns are guarded on the model: this service
        // is their only writer and a request body must never be one.
        $change->forceFill([
            'revert_attempts' => $attempts,
            // ⚠️ **CLEARED ON EXHAUSTION RATHER THAN LEFT AT A PAST TIME**, so
            // that a row nobody is retrying does not read as one that is due.
            'revert_attempt_after' => $exhausted ? null : CarbonImmutable::now()->addHours($this->backoffHours($attempts)),
            'revert_attempts_exhausted_at' => $exhausted ? CarbonImmutable::now() : null,
        ])->save();

        if ($exhausted) {
            $this->tellTheOwnerWeHaveStopped($change);
        }
    }

    /**
     * How long to wait after the nth failed attempt.
     *
     * ⚠️ **THE LAST ENTRY REPEATS.** A schedule shorter than the ceiling would
     * otherwise fall off its own end into an undefined index, which is a fatal
     * on the one path whose whole job is to keep trying.
     */
    private function backoffHours(int $attempts): int
    {
        $schedule = $this->revertBackoffHours();

        return $schedule[min($attempts, count($schedule)) - 1];
    }

    /**
     * Tell the owner we have stopped trying to take our change off their page.
     *
     * ⛔ **THE FACT THE CARD ALREADY READS, WITH A DIFFERENT SENTENCE ON IT.**
     * `SiteChanges::history()` decides that a change is *"still on your website
     * and we could not take it back off"* by looking for an `OwnerActionNeeded`
     * item carrying a `site_change_id`, and `ChangeMeasurer` files the first one
     * on the first failure. **What nothing said until now is that the platform
     * had stopped**, which is the part an owner has to act on: until this
     * message, waiting was a reasonable thing for them to do.
     *
     * ⚠️ **ONCE, BECAUSE THE EXHAUSTION STAMP IS SET IN THE SAME BREATH.** A
     * row past its ceiling never re-enters the due set, so there is no second
     * failure to file a second item from — 5746's card-a-night failure cannot
     * occur here, and it is the stamp rather than a guard that makes that true.
     */
    private function tellTheOwnerWeHaveStopped(SiteChange $change): void
    {
        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            $change->location_id,
            [
                'automation' => 'actuation.revert_attempts_exhausted',
                'site_change_id' => (int) $change->id,
                'change_type' => $change->change_type,
                'url' => $change->url,
                'attempts' => $this->registry->int('sites.revert.attempt_ceiling'),
            ],
            'A change on your website needs you to take it off',
        );
    }

    /**
     * The due set, up to `$limit` rows whose page we can still name.
     *
     * ⛔ **PAGED RATHER THAN LIMITED, AND {@see self::SWEEP_SCAN_PAGES} SAYS
     * WHY.** A single `LIMIT` before {@see self::identifiable()}'s filter lets a
     * block of rows nobody can act on hold the front of the queue for ever.
     *
     * ⚠️ **THE CURSOR IS THE `id`, WHICH IS ALSO THE ORDER.** So a row this run
     * skipped is still ahead of the ones it did not reach, and the remainder is
     * left in the same order for the next run rather than reshuffled.
     *
     * @param  Builder<SiteChange>  $query
     * @return list<int>
     */
    private function identifiableUpTo(Builder $query, int $limit): array
    {
        $ids = [];
        $after = 0;

        for ($page = 0; $page < self::SWEEP_SCAN_PAGES && count($ids) < $limit; $page++) {
            /** @var Collection<int, SiteChange> $rows */
            $rows = (clone $query)
                ->where('id', '>', $after)
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'location_id', 'url']);

            if ($rows->isEmpty()) {
                break;
            }

            $after = (int) $rows->last()->id;

            foreach (self::identifiable($rows) as $id) {
                if (count($ids) >= $limit) {
                    break;
                }

                $ids[] = $id;
            }
        }

        return $ids;
    }
}
