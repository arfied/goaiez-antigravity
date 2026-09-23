<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\CmsAdapter;
use App\Enums\ActuationTier;
use App\Enums\AutopilotActionType;
use App\Enums\GrowthPageStatus;
use App\Enums\PublishRefusal;
use App\Enums\SpeedFix;
use App\Exceptions\GrowthPageRefused;
use App\Jobs\AutopilotJob;
use App\Jobs\Content\PublishGrowthPageJob;
use App\Models\ActivityFeedItem;
use App\Models\Business;
use App\Models\Location;
use App\Notifications\GrowthPageHoldOpened;
use App\Notifications\GrowthPageReadyToPaste;
use App\Services\ActivityService;
use App\Services\Actuation\ActuationActor;
use App\Services\Actuation\ActuationTiers;
use App\Services\Actuation\ChangeSet;
use App\Services\Actuation\SiteChanges;
use App\Services\AuditService;
use App\Services\Billing\Subscriptions;
use App\Services\Config\DefaultsRegistry;
use App\Services\Indexing\Indexing;
use App\Services\Indexing\PageMarkup;
use App\Services\Mail\PlatformMailer;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * Steps 3–7 of Master 12.4's publishing pipeline: **gate → AUTO-WITH-HOLD →
 * snapshot → apply → say so.**
 *
 * `BUILD-PLAN` §2.11.3 slice D. {@see PublishGrowthPageJob} is
 * the caller; this class is where the order lives, because the order is the
 * safety argument and a job's `handle()` is not where an argument should be
 * kept.
 *
 * ## Steps 1 and 2 are not here and must not be added
 *
 * ⛔ **NO GENERATOR AND NO ORCHESTRATOR** (`BUILD-PLAN` §2.11.5 conflict 5).
 * Master 12.4's DIAGNOSE and the content engine proper are Stage 5. This
 * publishes a candidate somebody else produced — a fixture, a manual entry, and
 * later a generator plugged into the same pipe. Building one here to satisfy a
 * gate verb absorbs row 12.
 *
 * ## `execute()` and `handoff()` are one implementation
 *
 * ⚠️ **T4 IS THE HAND-OFF AND THE HAND-OFF IS T4** (`41` Part 1, `CLAUDE.md`'s
 * rule 44). {@see self::attempt()} runs every gate for both, and the last step
 * forks: a site-writing tier opens a change set, and T4 hands the owner the same
 * gated copy to paste. One implementation, because a location with no write
 * access and a location whose adapter is down want exactly the same thing.
 *
 * ## What is deliberately gated where
 *
 * ⛔ **`paused_at` IS NOT CHECKED HERE AND THAT IS NOT AN OMISSION.**
 * {@see AutopilotJob::handle()} refuses a paused or suspended tenant
 * before any side effect and records a `skipped` run — row 5's *"all sending +
 * actuation off"*, which this chain is the actuation half of. A second copy here
 * would be 398's shape from the other side: two guards for one rule, and the
 * inner one unfalsifiable once the outer refuses first.
 *
 * ⛔ **`actuation.enabled` GATES SITE WRITES AND NOTHING ELSE** (5665). It seeds
 * false, so while it is off no tier that touches a website may run.
 * ⚠️ **THE CLAUSE "AND NAMES SLICE H — MEASURE AND AUTO-ROLLBACK — AS WHAT IT
 * WAITS FOR" IS REMOVED: SLICE H MERGED 2026-08-20** (5800–5819), and a row
 * that goes on naming a merged slice as its precondition is the thing an
 * operator reads while pressing the switch (6184). ⚠️ **AND "IT SEEDS FALSE" IS
 * A FACT ABOUT AN UNSET ROW AND NEVER ABOUT AN INSTALL** (6121). It does **not**
 * refuse the advisory: T4 transmits nothing
 * ({@see ActuationTier::writesToTheSite()} is false for it), and switching off
 * the tier that changes no website would leave the hand-off half of rule 44
 * untestable on the only deployment that exists.
 */
final class Publishing
{
    /**
     * The switch `BUILD-PLAN` §2.11.3 assumed and nothing created (5652, 5665).
     *
     * ⛔ **IT SEEDS `false`, AND A SEED IS WHAT AN UNSET ROW ANSWERS RATHER THAN
     * WHAT AN INSTALL HAS ON** (6121). ⚠️ **This docblock read "IT SEEDS `false`
     * AND NAMES WHAT IT WAITS FOR — SLICE H MERGED", which contradicted itself
     * inside one sentence**: it waited for slice H, and H merged 2026-08-20
     * (5800–5819). The ordering argument it was made on is not withdrawn —
     * *"nothing may actuate a real site until H can revert one"*, because write
     * access without a proven revert is the liability `29` rule 32 names — it
     * is **satisfied**, which is why the row no longer names a precondition it
     * has met. What still has to be true for a write to happen is asked at write
     * time by {@see self::canWriteToSite()}, per location, and never asserted
     * here.
     */
    public const string SWITCH_KEY = 'actuation.enabled';

    /**
     * `29` §4.5's AUTO-WITH-HOLD table: *"New pages or blog posts — 24 hours —
     * first look at anything published under their name."*
     *
     * ⚠️ **A CONSTANT RATHER THAN A REGISTRY ROW**, on
     * `ActuationTiers::SIGHTING_WINDOW_DAYS`' reasoning: it is the published
     * behaviour of a documented automation pattern, and an Ops row would let a
     * window be shortened to nothing without the owner-facing copy that names it
     * moving with it.
     */
    public const int HOLD_HOURS = 24;

    /**
     * Which of the two *"a page is waiting on you"* states a feed row is about
     * (6292, closed at 6640).
     *
     * ⛔ **TWO WRITERS IN THIS FILE FILED BYTE-IDENTICAL BAGS FOR TWO DIFFERENT
     * OWNER ACTIONS.** {@see self::stop()} — the owner replied STOP, so the page
     * is held and somebody has to decide (5565) — and {@see self::handOver()} —
     * the T4 rung, where the copy was emailed for the owner to paste — both
     * filed `['automation' => 'content.publish_growth_page', 'growth_page_id' =>
     * …]`, because `handOver()`'s `$automationKey` **is** that same string. The
     * feed could not tell an owner which of the two had happened, so it shipped
     * the one sentence true of both rather than guessing.
     *
     * ⛔ **A KEY RATHER THAN A SECOND AUTOMATION NAME** — 6062's rule, which
     * {@see OwnerAttention} takes whole: the automation name is a string that
     * lives in whichever file files the row, and matching on it is 5817's rename
     * hazard, a sentence that silently stops appearing with the build green.
     *
     * ⚠️ **CONSTANTS RATHER THAN LITERALS, AND THE READER IMPORTS THEM.**
     * `OwnerAttention` already reaches `PublishingVolume::MAX_POSTS_KEY` for
     * exactly this reason: a shared literal is a rename away from a dead `match`
     * arm, and a shared constant cannot be. `Architecture/ActivityTest` pins the
     * literals here as well, which catches the removal a constant cannot.
     *
     * ⚠️ **AN UNRECOGNISED VALUE FALLS BACK TO THE SENTENCE TRUE OF BOTH**
     * rather than to either arm — so a third state added later reads as the gap
     * it is instead of telling an owner the wrong one of two things.
     */
    public const string WAITING_KEY = 'waiting_because';

    /** The owner replied STOP. Nothing republishes it on a clock (5565). */
    public const string WAITING_OWNER_STOPPED = 'owner_stopped';

    /** T4: the copy was emailed and the owner has to paste it (`41` Part 1). */
    public const string WAITING_HANDED_TO_OWNER = 'handed_to_owner';

    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly GrowthPages $pages,
        private readonly PublishingVolume $volume,
        private readonly AuthorByline $byline,
        private readonly SiteChanges $siteChanges,
        private readonly ActuationTiers $tiers,
        private readonly CmsAdapter $adapter,
        private readonly DefaultsRegistry $registry,
        private readonly Subscriptions $subscriptions,
        private readonly Indexing $indexing,
        private readonly PlatformMailer $mailer,
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
    ) {}

    /**
     * Whether the full path — a write to the tenant's own website — is open.
     *
     * ⛔ **THREE CONDITIONS AND THE THIRD IS WHAT MAKES THE ORDERING TRUE RATHER
     * THAN ASSERTED** (5665). A registry switch is an operator's promise; the
     * adapter's own {@see CmsAdapter::health()} is a fact. `LogCmsAdapter`
     * answers *"this deployment writes to no website"*, and its `snapshot()`
     * returns empty so {@see SiteChanges::open()} would refuse anyway — so on
     * every deployment that exists today, flipping the switch on by hand still
     * actuates nothing. The switch is the operator's half; this is the code's.
     */
    public function canWriteToSite(Location $location): bool
    {
        if ($this->registry->value(self::SWITCH_KEY) !== true) {
            return false;
        }

        $tier = $this->tiers->for($location);

        if ($tier === null || ! $tier->writesToTheSite()) {
            return false;
        }

        return $this->adapter->health($location)->writable;
    }

    /**
     * Whether the adapter bound on **this deployment** can write to a website.
     *
     * ⛔ **IT EXISTS BECAUSE NO STRING IN THIS REPOSITORY CAN ANSWER THAT
     * QUESTION AND ONE WAS ANSWERING IT ANYWAY** (6121, 6184).
     * `actuation.enabled`'s Ops description read *"the only adapter that exists
     * reports itself unwritable, so turning this on today changes nothing"* —
     * the sentence an operator reads **while** authorising writes to customers'
     * websites — and `CMS_DRIVER=wordpress` had been deployed to production
     * hours before anybody noticed (5913). A seed is not a deployment, and a
     * `.env` value is not something a manifest can see. **This runs inside the
     * deployment, so it can just ask.**
     *
     * ⚠️ **IT ASKS THE ADAPTER RATHER THAN NAMING ADAPTERS**, which is what
     * makes it survive the next one. A `match` over `$this->adapter::class` would
     * be a second list of implementations to forget to extend — 272's shape on an
     * interface — and every fake in the test suite would fall off the end of it.
     * {@see CmsAdapter::fieldSupport()} is on the contract, costs no network by
     * its own docblock's rule, and is the verb whose entire job is *"what can you
     * write"*.
     *
     * ⚠️ **THE FIELD LIST IS EVERY FIELD THIS APPLICATION EVER WRITES**, page
     * fields and speed-fix fields together, because *"can this deployment change
     * a website at all"* is not the same question as *"can it publish a growth
     * page"*. An adapter that could only apply a speed fix would answer `false`
     * to the narrower question and would still be writing to somebody's site.
     *
     * ⛔ **IT IS NOT A GATE AND MUST NOT BECOME ONE.**
     * {@see self::canWriteToSite()} is the gate, it asks
     * {@see CmsAdapter::health()} per location, and it is what every write goes
     * through. This answers a question an operator asks about the whole
     * install — no location, no network — so a caller that used it to decide
     * whether to write would be gating a page on a property of the binding.
     */
    public function adapterReachesAWebsite(): bool
    {
        $fields = [
            ...self::FIELDS,
            ...array_map(static fn (SpeedFix $fix): string => $fix->field(), SpeedFix::cases()),
        ];

        return $this->adapter->fieldSupport($fields)->writable !== [];
    }

    /**
     * Whether a registry key is the site-write switch (5885).
     *
     * ⚠️ **IT EXISTS SO THAT NOTHING ELSE HAS TO SPELL THE KEY.** The Ops editor
     * has to recognise this one switch among a hundred and twenty-five settings,
     * and a `$key === 'actuation.enabled'` there would be a second file naming
     * it — which is one file too many for `ActuationTest`'s *"the site-write
     * switch is named in one service and one manifest, and nowhere else"* to be
     * able to fail on anything. A lint with two legitimate occupants cannot tell
     * a third from a typo.
     */
    public function isSiteWriteSwitch(string $key): bool
    {
        return $key === self::SWITCH_KEY;
    }

    /**
     * Turn site writes on — the only way `actuation.enabled` ever becomes true
     * (5883, decision 220's pattern).
     *
     * ⛔ **THE LITERAL `true` IS THE VALUE, SO THERE IS NO `if` TO DELETE.**
     * Decision 220's argument, applied to the switch that authorises writing to
     * a stranger's website: *"the usual way to honour it is an `if ($confirmed)`
     * inside the method. That is a line somebody can delete, and no test
     * necessarily notices."* PHP's literal `true` type means a caller holding a
     * plain `bool` **cannot call this at all** — they have to narrow it first,
     * which is the moment they must actually check that a person pressed the
     * second button. The failure is a `TypeError` from PHP and an error from
     * Larastan, with no code of ours involved. Writing `$confirmed` rather than
     * a fresh `true` goes one step further: there is not even a literal here for
     * the parameter to fail to guard.
     *
     * ⛔ **AND A LINT MAKES THIS THE ONLY DOOR.** `ActuationTest`'s *"the
     * site-write switch is named in one service and one manifest, and nowhere
     * else"* fails the build on `actuation.enabled` written anywhere else in
     * `app/` — it is the same case as the one two methods up, and both
     * paragraphs used to quote it under two different invented names (9780) —
     * on decision 285's
     * reasoning: a required parameter of a type a caller cannot fabricate is
     * worthless if the caller can simply write the registry row instead.
     *
     * ⚠️ **IT IS A CONFIRMATION OF INTENT AND NOT AN ATTESTATION** (5884).
     * `voice.enabled` is refused inside {@see DefaultsRegistry::set()} until an
     * operator has *recorded a statement about a fact outside this application*
     * — a clip configured on Infobip's number setup, which no code here can
     * check. Both facts that make a site write real are already checked by
     * {@see self::canWriteToSite()} in the two lines above, so an attestation
     * here would be an operator swearing to something the machine verifies for
     * itself: a record that reads as evidence and proves nothing. What is
     * missing is not evidence of an external step; it is that one press
     * authorises writing to somebody else's website.
     */
    public function authoriseSiteWrites(string $actor, true $confirmed): void
    {
        $this->registry->set(self::SWITCH_KEY, $confirmed, $actor);
    }

    /**
     * Turn site writes off.
     *
     * ⚠️ **NO CONFIRMATION, DELIBERATELY, AND THE ASYMMETRY IS THE POINT** —
     * {@see DefaultsRegistry::assertPreconditionsMet()} makes the same call for
     * `voice.enabled`: *"a switch you cannot turn off in an incident is worse
     * than the thing it was protecting against."* The ceremony guards the
     * direction that creates the liability, and the way back from a mistake must
     * cost one press.
     */
    public function withdrawSiteWriteAuthority(string $actor): void
    {
        $this->registry->set(self::SWITCH_KEY, false, $actor);
    }

    /**
     * Run the pipeline once for one page.
     *
     * ⚠️ **IDEMPOTENT AT EVERY STEP, BECAUSE THE JOB RETRIES AND THE SWEEP
     * RE-DISPATCHES.** A page that already cleared the gate is not re-gated (the
     * model call is the expensive half); a page already on a hold is not
     * re-notified; a published page is answered with
     * {@see PublishRefusal::AlreadyPublished} rather than a second change set.
     *
     * @param  bool  $writesToSite  What {@see self::canWriteToSite()} answered,
     *                              passed in rather than re-asked so that
     *                              `execute()` and `handoff()` cannot disagree
     *                              with the decision that chose between them.
     */
    public function attempt(
        int $pageId,
        ContentEvidence $evidence,
        bool $writesToSite,
        string $automationKey,
    ): PublishOutcome {
        $candidate = $this->pages->candidate($pageId);

        if ($candidate === null) {
            throw GrowthPageRefused::missing($pageId);
        }

        if ($candidate->isPublished()) {
            return PublishOutcome::refused(PublishRefusal::AlreadyPublished);
        }

        $location = Location::query()->find($candidate->locationId);

        if (! $location instanceof Location
            || $location->website_url === null
            || $location->website_confirmed_at === null) {
            return PublishOutcome::refused(PublishRefusal::NoConfirmedWebsite);
        }

        $business = Business::query()->find(Tenancy::idOrFail());

        if (! $business instanceof Business || ! $this->subscriptions->isEntitled($business)) {
            return PublishOutcome::refused(PublishRefusal::NotEntitled);
        }

        if ($location->content_generation_paused_at !== null) {
            return PublishOutcome::refused(PublishRefusal::SelfAuditPaused);
        }

        $volume = $this->volume->verdict($this->pages, $candidate->locationId, $candidate->type, Carbon::now());

        if (! $volume->allowed) {
            $this->tellTheOwnerTheCapIsSpent($candidate->locationId, $automationKey, $volume);

            return PublishOutcome::refused(PublishRefusal::VolumeCapReached);
        }

        $gate = $this->gateIfNeeded($candidate, $evidence);

        if ($gate !== null) {
            return PublishOutcome::refused($gate);
        }

        if (! $writesToSite) {
            return $this->handOver($candidate, $location, $automationKey);
        }

        $hold = $this->holdVerdict($candidate, $location);

        if ($hold !== null) {
            return PublishOutcome::refused($hold);
        }

        return $this->writeToTheSite($candidate, $location);
    }

    /**
     * The owner replied STOP. Stop.
     *
     * ⛔ **THE HOLD STOPS LAPSING AND THE PAGE STARTS WAITING FOR A PERSON**
     * (5565). Nothing republishes it on a clock afterwards; somebody has to
     * decide. That is `29` §2 rule 31's *"expiry defaults to hold, never
     * publish"* applied to the one state where the two rules meet.
     *
     * @return bool Whether this call was the one that stopped it.
     */
    public function stop(int $pageId, string $actor): bool
    {
        $stopped = $this->pages->holdForOwner($pageId);

        if (! $stopped) {
            return false;
        }

        $candidate = $this->pages->candidate($pageId);

        // `29` §2 rule 42: a decision to withhold something this platform was
        // about to publish under the owner's name is a sensitive action.
        $this->audit->record('growth_page.held_by_owner', $actor, null, [
            'growth_page_id' => $pageId,
            'location_id' => $candidate?->locationId,
        ]);

        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            $candidate?->locationId,
            [
                'automation' => 'content.publish_growth_page',
                'growth_page_id' => $pageId,
                // ⛔ **THE HALF `handOver()` COULD NOT BE TOLD APART FROM**
                // (6292). Without this the feed reads the same for a page the
                // owner stopped and a page we emailed them to paste.
                self::WAITING_KEY => self::WAITING_OWNER_STOPPED,
            ],
        );

        return true;
    }

    /**
     * Gate the page, unless it has already been through.
     *
     * ⚠️ **RE-GATING IS NOT FREE AND THAT IS WHY THIS ASKS FIRST** (5576). Every
     * moderation call debits the tenant's AI pool, and the sweep re-dispatches
     * this pipeline for every page whose hold lapses — so an unconditional
     * re-gate would charge a tenant again for a verdict this application already
     * holds, on a page nobody has edited.
     */
    private function gateIfNeeded(PublishCandidate $candidate, ContentEvidence $evidence): ?PublishRefusal
    {
        if ($this->pages->hasClearedTheGate($candidate->id)) {
            return null;
        }

        $outcome = $this->pages->gateCandidate($candidate->id, $evidence);

        return $outcome->cleared() ? null : PublishRefusal::GateHeld;
    }

    /**
     * Where this page is in its 24-hour window, or null if the window is done.
     *
     * ⛔ **IF NO NOTICE CAN BE DELIVERED, THE HOLD IS NEVER OPENED** — and a
     * hold that was never opened never lapses, so nothing publishes. `29` §4.5
     * makes AUTO-WITH-HOLD *"proceeds unless they reply STOP within the
     * window"*, and silence is consent only if the owner could have broken it.
     * Opening the window on a mail system that delivers nothing would be
     * publishing to a stranger's website on the strength of a question nobody
     * was asked.
     *
     * ⚠️ **`canDeliver()` IS NOT A DELIVERY RECEIPT** and its own docblock says
     * so — it answers *"is the mail system configured to send at all"*. A
     * rejected recipient past this point still leaves a hold running on a notice
     * nobody read; closing that needs the bounce feed (open question H), and
     * `FirstWeekPath` records the identical limit rather than implying this gate
     * is more than it is.
     *
     * ## ⛔ The hold is written AFTER the notice, and it was written before
     *
     * ⛔ **THE PARAGRAPH ABOVE WAS TRUE OF `canDeliver()` AND FALSE OF THE SEND
     * — CORRECTED 2026-08-28 (11010).** `holdCandidate()` stamped `hold_until`
     * and *then* handed the notice to {@see PlatformMailer::send()}, which
     * returns `void` and swallows every throwable: **a message never handed to
     * the queue was indistinguishable from one delivered**, and twenty-four
     * hours later `content:release-holds` published the page. The sentence
     * three paragraphs up — *silence is consent only if the owner could have
     * broken it* — was in this docblock the whole time, guarding the one arm
     * `canDeliver()` can answer and none of the arms it cannot.
     *
     * ✅ **{@see PlatformMailer::deliverNow()} RATHER THAN `send()`, AND THE
     * `holdCandidate()` BELOW IS WHY** — 10852's shape, the digest's own
     * repair. It **throws** where `send()` swallows, so the hold is only
     * written on the far side of a transport that took the message. The order
     * is the whole fix: a state write that asserts a message went out belongs
     * after the send, never before it (`MailQuota::record()`'s own rule).
     *
     * ⚠️ **`canDeliver()` STAYS, AND IT MAKES `deliverNow()`'s FIRST ARM
     * UNREACHABLE FROM HERE — DELIBERATELY.** Nothing changes the mail
     * configuration between the two calls, so `assertDeliverable()` inside
     * `deliverNow()` can no longer refuse anything this method reached. That is
     * not 398's shape, because the two are not asking one question: the outer
     * ask keeps *"this deployment sends no mail at all"* a typed refusal on a
     * `skipped`-shaped run rather than an hourly failed job per page, and what
     * the inner call adds is every arm the outer one **structurally cannot
     * see** — the stated 24-hour ceiling, the headroom against it, and the
     * transport actually refusing the message.
     *
     * ⚠️ **WHAT RINGS CHANGED, AND SAYING SO IS PART OF THE MOVE.** This caller
     * has left `DeliverPlatformMail` entirely, so
     * `OperatorAlertKind::PlatformMailUndeliverable` — raised only inside that
     * job's `failed()` — no longer covers it. What covers it now is
     * {@see AutopilotJob::failed()}'s `AutomationAbandoned`, which is **wider**
     * rather than a consolation: it also covers the never-queued population
     * that had no bell at all, because that was the arm `send()`'s `catch`
     * logged and told nobody about.
     *
     * ⚠️ **AND `deliverNow()` RETURNING IS STILL NOT A DELIVERY.** It means the
     * transport accepted the message, which is strictly more than *the queue
     * accepted a job* and strictly less than *the owner read it*. A hard bounce
     * on the owner's own address past this point still leaves a hold running on
     * a notice nobody read — see 11013 for why that read is not added here.
     */
    private function holdVerdict(PublishCandidate $candidate, Location $location): ?PublishRefusal
    {
        if ($candidate->releasesOnSilence()) {
            return $candidate->holdUntil?->isFuture() === true
                ? PublishRefusal::AwaitingHold
                : null;
        }

        // Held, with no release time: the gate's hold, or the owner's STOP.
        // Both wait for a person and neither is this sweep's to lift.
        if ($candidate->status === GrowthPageStatus::Held) {
            return PublishRefusal::OwnerHeld;
        }

        if (! $this->mailer->canDeliver()) {
            return PublishRefusal::OwnerNoticeUndeliverable;
        }

        $address = $this->ownerAddress();

        if ($address === null) {
            return PublishRefusal::OwnerNoticeUndeliverable;
        }

        $until = Carbon::now()->addHours($this->holdHours());

        // ⛔ **THE NOTICE FIRST AND THE HOLD SECOND, AND THE TWO LINES WERE THE
        // OTHER WAY ROUND UNTIL 11010.** `deliverNow()` throws, so a page whose
        // notice the transport would not take acquires no `hold_until` at all —
        // and a hold that never opened never lapses, which is the property this
        // method's docblock has claimed since the day it was written.
        $this->mailer->deliverNow($address, new GrowthPageHoldOpened(
            $candidate->copy->title,
            $candidate->urlOn((string) $location->website_url),
            $until,
            $candidate->id,
            (int) $location->business_id,
        ));

        // ⚠️ **A CRASH BETWEEN THE TWO SENDS THE NOTICE TWICE AND PUBLISHES
        // NOTHING**, which is the direction to fail in. The retry re-runs this
        // method from the top — the gate is not re-paid for
        // ({@see self::gateIfNeeded()}) — and the owner gets a second copy of
        // *"this goes live tomorrow"* about a page that is not yet on a clock.
        // The alternative ordering loses no message and publishes to a
        // stranger's website on the strength of a question nobody was asked.
        $this->pages->holdCandidate($candidate->id, $until);

        return PublishRefusal::AwaitingHold;
    }

    /**
     * The T4 rung: the owner gets the page, ready to paste.
     *
     * ⛔ **THE FEED ROW BELOW SAYS *"WE HAVE EMAILED YOU A PAGE"* AND UNTIL
     * 11011 IT SAID SO ABOUT A MESSAGE NOBODY HAD ACCEPTED.**
     * {@see OwnerAttention} renders `WAITING_HANDED_TO_OWNER` as an
     * unconditional claim about a send, and the send was
     * {@see PlatformMailer::send()} — `void`, swallowing every throwable. **A
     * state write asserting a message went out is the one caller shape
     * {@see PlatformMailer::canDeliver()} was built for**, and `canDeliver()`
     * answers a `.env` question that is identical for every business in a
     * sweep; it cannot see a dispatch that failed.
     *
     * ✅ **{@see PlatformMailer::deliverNow()}, AND THE FEED ROW AFTER IT.**
     * The throw is what stops the sentence being written, the claim being
     * spent, and the run closing `handed_off` — see
     * {@see PublishGrowthPageJob::claimIsSpent()}, which is answered from the
     * outcome this method returns, so an exception hands the claim back and the
     * page can be attempted again.
     *
     * ⚠️ **THIS IS THE LIVE ARM ON EVERY DEPLOYMENT THAT EXISTS.**
     * {@see self::canWriteToSite()} is false wherever `LogCmsAdapter` is bound,
     * so T4 is what a growth page actually reaches — which is why the ordering
     * here matters as much as the hold's, even though nothing is written to
     * anybody's website on this path.
     *
     * ⚠️ **AND `handOver()` LOSES `PlatformMailUndeliverable` EXACTLY AS THE
     * HOLD DOES.** `AutomationAbandoned` is what rings; see
     * {@see self::holdVerdict()}'s docblock for the whole of that trade.
     */
    private function handOver(PublishCandidate $candidate, Location $location, string $automationKey): PublishOutcome
    {
        // ⚠️ **THE BYLINE RIDES ALONG WHEN THERE IS ONE AND NEVER GATES THIS
        // RUNG** (5745). Rule 36 is about content *this platform* publishes, and
        // T4 publishes nothing — the owner does. So an advisory carries the line
        // when the tenant has given us one to carry, and a tenant who has not is
        // still handed their copy rather than being refused the only tier that
        // works today. {@see AuthorByline::stored()} rather than `verify()`: this
        // path sends an email and must not depend on the tenant's website being
        // up.
        $advisory = PageAdvisory::for($candidate, (string) $location->website_url, $this->byline->stored($location));

        if (! $this->mailer->canDeliver()) {
            return PublishOutcome::refused(PublishRefusal::OwnerNoticeUndeliverable);
        }

        $address = $this->ownerAddress();

        if ($address === null) {
            return PublishOutcome::refused(PublishRefusal::OwnerNoticeUndeliverable);
        }

        // ⛔ **BEFORE THE FEED ROW, NEVER AFTER IT** (11011). The sentence
        // `OwnerAttention` renders is *"We have emailed you a page"*, and a
        // swallowed send made that a claim about nothing.
        $this->mailer->deliverNow($address, new GrowthPageReadyToPaste($advisory));

        // ⚠️ **`OwnerActionNeeded` RATHER THAN `ContentPublished`.** Nothing is
        // on the website: the owner has to paste it, which is the definition of
        // the case and the reason `41` Part 1 requires T4 be described honestly
        // rather than as a quieter version of publishing.
        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            $candidate->locationId,
            [
                'automation' => $automationKey,
                'growth_page_id' => $candidate->id,
                // ⚠️ **AND THE AUTOMATION KEY IS WHY THIS IS NEEDED AT ALL**:
                // `$automationKey` is `content.publish_growth_page` on the
                // ordinary path, which is byte-identical to `stop()`'s (6292).
                self::WAITING_KEY => self::WAITING_HANDED_TO_OWNER,
            ],
        );

        return PublishOutcome::handedOff($advisory);
    }

    /**
     * Snapshot, open the change set, write, and only then say it happened.
     *
     * ⛔ **THE CHANGE SET IS OPENED BEFORE THE WIRE CALL** — `29` §2 rule 32,
     * enforced by {@see SiteChanges::open()} refusing an empty snapshot rather
     * than by this method remembering to. An empty snapshot here is an adapter
     * that read nothing, and publishing over a page we could not read is the one
     * outcome an owner cannot be given back.
     */
    private function writeToTheSite(PublishCandidate $candidate, Location $location): PublishOutcome
    {
        $tier = $this->tiers->for($location);

        if ($tier === null) {
            return PublishOutcome::refused(PublishRefusal::NoConfirmedWebsite);
        }

        // ⛔ **RULE 36's GATE, BEFORE ANYTHING IS READ OR WRITTEN** (5720, 5729,
        // and 5677's *"it is owed before G"*). *"Auto-published content carries a
        // real author byline linked to a genuine About page"*, and the owner's
        // ruling is that **no About page means no publish**. It sits above the
        // snapshot rather than beside the write so that a page with no byline
        // never opens a change set at all — a `site_changes` row for a change we
        // were never allowed to make is a row slice J offers an owner an Undo
        // for.
        // ⚠️ **`check()` RATHER THAN `verify()`, AND THE GATE IS THE SAME GATE**
        // (6065). `verify()` is one line of `check()` and answers null for both
        // shapes of failure; what the fuller answer carries is **why**, which is
        // the difference between telling somebody their About page has stopped
        // working and telling them we were not allowed to look at it.
        $check = $this->byline->check($location);

        if ($check->byline === null) {
            $this->tellTheOwnerTheBylineIsMissing($candidate, $location, $check);

            return PublishOutcome::refused(PublishRefusal::NoAuthorByline);
        }

        $byline = $check->byline;

        $url = $candidate->urlOn((string) $location->website_url);

        // ⛔ **ASK THE ADAPTER WHAT IT CAN WRITE BEFORE BUILDING A SET THAT
        // NAMES IT** (5753(a), 5772). This method named `meta_description`
        // unconditionally, and every adapter that refuses an unwritable field
        // refuses the set **whole** — so on the only live adapter there is, every
        // publish opened a `site_changes` row and then failed. The field is
        // dropped **with its reason recorded**, never silently.
        $support = $this->adapter->fieldSupport(self::FIELDS);

        $writable = $support->writable;

        // ⛔ **A PAGE IS ITS TITLE AND ITS BODY.** Withholding a meta description
        // publishes a page that is missing a nicety; withholding either of these
        // would publish a page that is missing the page. Refused rather than
        // degraded, because `29` §2 rule 43's surviving half is graceful
        // degradation and an empty page is not a degraded page.
        foreach (self::REQUIRED_FIELDS as $required) {
            if (! $support->permits($required)) {
                return PublishOutcome::refused(PublishRefusal::PageFieldsNotWritable);
            }
        }

        $snapshot = $this->siteChanges->snapshot($location, $url, $writable);

        // ⛔ **THREE READINGS AND ONLY TWO OF THEM PROCEED** (5770). *Absent* —
        // we asked the site and nothing is published at this address — is a
        // **creation**, and it is what a growth page almost always is. *Read* is
        // an edit of the page that is there. Everything else is *"we could not
        // see the site"*, and publishing over a page we could not read is the
        // one outcome an owner cannot be given back.
        if (! $snapshot->state->permitsAChangeSet()) {
            return PublishOutcome::refused(PublishRefusal::SiteUnreadable);
        }

        $actor = ActuationActor::autopilot();

        $intended = $this->pageFields($candidate, $byline);

        $after = array_intersect_key($intended, array_flip($writable));

        // ⚠️ **WITHHELD IS WHAT THIS PAGE WANTED AND COULD NOT HAVE, NOT EVERY
        // FIELD THE ADAPTER REFUSES.** A page with no meta description of its
        // own has had nothing withheld from it, and recording one would put a
        // refusal on the row for a value nobody ever wrote.
        $withheld = array_intersect_key($support->refused, $intended);

        $change = $this->siteChanges->open(
            $location,
            $snapshot->isAbsent()
                ? ChangeSet::creating($url, 'growth_page', $tier, $after, $withheld)
                : new ChangeSet($url, 'growth_page', $tier, $snapshot->fields, $after, $withheld),
            $actor,
        );

        $outcome = $this->siteChanges->apply($change, $actor, AutopilotActionType::ContentPublished);

        if (! $outcome->ok) {
            return PublishOutcome::refused(PublishRefusal::AdapterWriteFailed);
        }

        $this->pages->markPublished($candidate->id);

        // ⛔ **AFTER THE PAGE IS ON THE SITE AND NEVER BEFORE** (slice E). An
        // announcement is a request that six search engines come and fetch this
        // URL; making it while the write could still fail is asking for a crawl
        // of a 404 we caused.
        //
        // ⚠️ **NOT ON THE HAND-OFF PATH EITHER.** T4 puts nothing on a website,
        // so `handOver()` announces nothing — there is no page there to find.
        //
        // ⚠️ **`PageMarkup::none()` IS THE TRUE ANSWER AND NOT A PLACEHOLDER.**
        // `growth_pages.schema_json` is deliberately absent until something
        // writes it (§2.11.1), and a growth page is neither a job posting nor a
        // livestream — so Google's Indexing API may not be used for it, which is
        // the routing this hands `Indexing` rather than a gap it papers over.
        $this->indexing->announce($location, $url, PageMarkup::none());

        return PublishOutcome::published($tier, $url, (int) $change->getKey());
    }

    /**
     * Tell the owner the one thing they can do about rule 36.
     *
     * ⛔ **WITHOUT THIS THE GATE IS SILENT AND THE OWNER CANNOT ACT ON IT**
     * (5746). *"No About page means no publish"* is a refusal a person has to be
     * able to fix, and the check runs in a queued job nobody is watching — so a
     * page that never publishes because a redesign 404'd one URL would otherwise
     * look exactly like a platform that had stopped working.
     *
     * ⚠️ **ONCE PER LOCATION PER MONTH**, on {@see self::tellTheOwnerTheCapIsSpent()}'s
     * reasoning and with the same predicate shape: the sweep re-dispatches every
     * page whose hold lapsed, and an item per attempt would fill a feed with one
     * sentence per page per hour.
     */
    private function tellTheOwnerTheBylineIsMissing(
        PublishCandidate $candidate,
        Location $location,
        BylineCheck $check,
    ): void {
        $alreadyTold = ActivityFeedItem::query()
            ->where('action_type', AutopilotActionType::OwnerActionNeeded->value)
            ->where('location_id', $candidate->locationId)
            ->whereRaw("metadata->>'needs' = ?", ['about_page'])
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->exists();

        if ($alreadyTold) {
            return;
        }

        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            $candidate->locationId,
            [
                'automation' => 'content.publish_growth_page',
                'needs' => 'about_page',
                // ⚠️ **WHETHER THEY HAVE TOLD US AT ALL IS THE DIFFERENCE BETWEEN
                // "paste your About page" AND "your About page stopped working"**,
                // and slice J's screen is where that sentence is written. The
                // fact travels; the wording does not live in a metadata bag.
                'about_url_confirmed' => $location->about_url_confirmed_at !== null,
                // ⛔ **THREE STATES, NOT TWO** (6065, and the defect half of
                // 5744). *"You have not told us"*, *"you told us and it has
                // stopped answering"* and *"you told us, it answers fine, and
                // your own website will not let us look at it"* are three
                // different things to be told and exactly one of them is fixed
                // in a `robots.txt`. Filing the third as the second asks a
                // tenant to re-paste a URL that was never the problem — which
                // they will do, and it will fail again, for ever.
                'about_page_reachable' => $check->reachable,
                // ⚠️ **AND WHOSE SETTING IT WAS.** A refusal has several causes
                // and only one of them is the tenant's to fix — a spent rate
                // budget is ours and clears itself. `AuthorByline`'s screen
                // sentence keys on this rather than on `about_page_reachable`,
                // so a tenant is never sent to edit a file that was not the
                // problem.
                'about_page_blocked_by_robots' => $check->ownRobotsRefused,
            ],
        );
    }

    /**
     * ⚠️ **ONE FEED ITEM PER LOCATION PER PERIOD, NOT ONE PER ATTEMPT.** The
     * sweep re-dispatches every page whose hold lapsed, so an unconditional
     * write would fill an owner's feed with the same sentence once per held
     * page, every hour, for the rest of the month.
     *
     * ⚠️ **THE AUTOMATION KEY IS PART OF THE PREDICATE**, on `AnalyzeReviewJob`'s
     * trap: `OwnerActionNeeded` is shared, and a future automation writing a
     * `cap` into its metadata would otherwise silence this one.
     */
    private function tellTheOwnerTheCapIsSpent(int $locationId, string $automationKey, VolumeVerdict $verdict): void
    {
        $alreadyTold = ActivityFeedItem::query()
            ->where('action_type', AutopilotActionType::OwnerActionNeeded->value)
            ->where('location_id', $locationId)
            ->whereRaw("metadata->>'automation' = ?", [$automationKey])
            ->whereRaw("metadata->>'cap' = ?", [(string) $verdict->capKey])
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->exists();

        if ($alreadyTold) {
            return;
        }

        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            $locationId,
            ['automation' => $automationKey] + $verdict->context(),
        );
    }

    /**
     * ⚠️ **THE OWNER'S ADDRESS, NEVER A LOCATION'S CONTACT EMAIL.**
     * `NotifyOwnerOfVoicemailJob`'s shape: the person who has to decide about a
     * page published under their name is the account owner, and a location
     * mailbox is often a shared inbox nobody watches.
     */
    private function ownerAddress(): ?string
    {
        $address = trim((string) Business::query()->find(Tenancy::idOrFail())?->owner?->email);

        return $address === '' ? null : $address;
    }

    /**
     * The fields a page change set is about, before the adapter is asked.
     *
     * ⚠️ **THE SAME SET ON BOTH SIDES**, so that {@see ChangeSet::inverted()} is
     * a genuine restoration rather than a partial one — §19.7's rollback
     * fidelity gate is slice H's, and it can only hold if the snapshot covers
     * everything the write touches. **That is why the snapshot is taken over the
     * *writable* subset rather than over this constant** (5772): a `before` that
     * carried a field the `after` never touches would make an undo write a value
     * nothing had changed.
     *
     * @var list<string>
     */
    private const array FIELDS = ['title', 'meta_description', 'content'];

    /**
     * The fields without which there is no page.
     *
     * ⚠️ **A SUBSET OF `FIELDS` AND NOT A SECOND LIST OF THEM** — a test asserts
     * that, because two lists that must agree are two lists that will not.
     *
     * @var list<string>
     */
    private const array REQUIRED_FIELDS = ['title', 'content'];

    /**
     * What this page says — every field it has a value for, before the adapter's
     * answer narrows it.
     *
     * ⚠️ **THE BYLINE IS PART OF THE CONTENT AND THEREFORE PART OF THE CHANGE
     * SET, WHICH IS WHAT MAKES IT REVERSIBLE.** Rule 32's `before` covers the
     * same fields, so an undo takes the byline off with the page it was on —
     * where a byline written by some second mechanism would survive the rollback
     * and leave a stranger's page signed by us.
     *
     * @return array<string, string>
     */
    private function pageFields(PublishCandidate $candidate, PageByline $byline): array
    {
        $fields = [
            'title' => $candidate->copy->title,
            'content' => $candidate->copy->content."\n\n".$byline->asHtml(),
        ];

        // ⚠️ **A PAGE WITH NO META DESCRIPTION ASKS FOR NOTHING, RATHER THAN
        // ASKING TO WRITE NOTHING.** `growth_pages.meta_description` is
        // nullable, and a change set carrying `null` would record a field in
        // `after_snapshot` that no adapter can write and no undo can restore.
        if ($candidate->copy->metaDescription !== null) {
            $fields['meta_description'] = $candidate->copy->metaDescription;
        }

        return $fields;
    }

    public function holdHours(): int
    {
        return $this->defaults->int('content.publishing.hold_hours');
    }
}
