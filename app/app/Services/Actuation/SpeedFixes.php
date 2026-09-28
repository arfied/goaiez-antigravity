<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Contracts\CmsAdapter;
use App\Enums\ActuationTier;
use App\Enums\SpeedFix;
use App\Enums\SpeedFixRefusal;
use App\Enums\SpeedFixStatus;
use App\Exceptions\TenantMismatch;
use App\Models\Location;
use App\Models\SpeedChangeSet;
use App\Services\Actuation\WordPress\WordPressAdapter;
use App\Services\Config\DefaultsRegistry;
use App\Services\Content\Publishing;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The speed layer's decision half — `28` §4.1 and §4.3's rollout rules:
 * *"each fix = one change set … snapshot first … fixes deploy one at a time per
 * site with ≥48h between, so regressions are attributable."*
 *
 * ⛔ **THE ONLY WRITER OF `speed_change_sets`**, held by an
 * `Architecture\ActuationTest` lint on `SiteChanges`' and
 * `SiteChangeQuarantines`' precedent (5521, 5812). What a second writer would
 * skip is the attribution rule: a fix inserted without going through
 * {@see self::apply()} is a second fix on a site whose first is still being
 * measured, and §4.3's whole argument for the seven being separate change sets
 * is that a regression can then be traced to one of them.
 *
 * ---------------------------------------------------------------------------
 * ⛔ NOT ONE OF THE SEVEN CAN BE WRITTEN BY ANY ADAPTER THAT EXISTS
 * ---------------------------------------------------------------------------
 * Every fix in §4.1 is theme- or asset-layer — a server-side filter, a
 * stylesheet line, an HTMLRewriter rule — and `BUILD-PLAN` §2.11.5 already
 * records that *"the seven speed fixes (L) … are theme- and admin-layer; neither
 * is reachable over REST."* Building this slice confirmed it against the one
 * live adapter: {@see WordPressAdapter} writes
 * over WordPress **core's** REST API and its entire writable vocabulary is
 * `title`, `content` and `excerpt`.
 *
 * ⚠️ **SO THE REFUSAL IS ASKED FOR RATHER THAN ASSUMED, AND THE SEAM IS ONE
 * THAT ALREADY EXISTED** (5772). {@see self::plan()} asks the bound adapter
 * `fieldSupport()` — the question `Publishing` was not asking when it named a
 * meta description core REST cannot write — and records
 * {@see SpeedFixRefusal::AdapterCannotWrite} against each fix. Nothing here has
 * to change on the day F2's plugin adapter lands: its `fieldSupport()` will
 * answer differently and the same seven fixes become applicable.
 *
 * ⛔ **AND THEIR CONTENT IS UNDERIVABLE TOO, WHICH IS THE LARGER HALF.** Even
 * granted a way to write, nothing here knows *what* to write: script deferral
 * needs the list of scripts a page loads, preconnect needs its third-party
 * origins, image work needs an image inventory. The pixel collects pageviews,
 * vitals, errors, scroll and form events and **no resource timings**, and
 * {@see SiteProbe} keeps two booleans from its fetch and none of the markup. So
 * {@see self::apply()} takes the fix's content from its caller, and **the caller
 * is F2** — the plugin is both the only thing that can see a site well enough to
 * compute a fix and the only thing that can apply one.
 *
 * ⚠️ **WHAT THIS IS NOT IS UNTESTED.** Every arm below is driven against a
 * `CmsAdapter` fake, exactly as slices D, G and H drive theirs.
 * ⛔ **THE CLAUSE THAT FOLLOWED — *"what is unreachable is production, which is
 * true of the whole actuation chain while `CMS_DRIVER` selects the log driver
 * and `actuation.enabled` seeds false"* — IS REMOVED RATHER THAN UPDATED
 * (6121, 6184).** A **seed** is what an unset row answers and `config(X, 'log')` is
 * what an absent `.env` line answers; neither is a statement about a running
 * install, and both were false in production for part of 2026-08-20 (5913).
 * **No docblock here can say what a deployment has on**, so this one no longer
 * tries.
 */
final class SpeedFixes
{
    /**
     * `28` §4.3: *"fixes deploy one at a time per site with ≥48h between, so
     * regressions are attributable."*
     */
    public const int MINIMUM_HOURS_BETWEEN_FIXES = 48;

    public function __construct(private readonly DefaultsRegistry $registry,
        private readonly CmsAdapter $adapter,
        private readonly ActuationTiers $tiers,
        private readonly SiteChanges $siteChanges,
        private readonly SiteChangeQuarantines $quarantines,
        private readonly Publishing $publishing,
        private readonly SiteMeasurements $measurements,
    ) {}

    /**
     * Every fix, and for each the reason it is not being applied to this site.
     *
     * ⚠️ **NOTHING HERE TOUCHES THE NETWORK, AND THAT IS A CONSTRAINT RATHER
     * THAN A PROPERTY** (5599). A screen renders this per location, so the live
     * *"is this site writable right now"* question — two HTTP requests to a
     * customer's website — belongs to {@see self::apply()} and is deliberately
     * absent here. `CmsAdapter::fieldSupport()` is on the contract as an
     * offline question for the same reason.
     *
     * @return list<SpeedFixOption>
     */
    public function plan(Location $location, ?ActuationTier $tier, ?CarbonImmutable $now = null): array
    {
        $this->assertSameTenant($location);

        $now ??= CarbonImmutable::now();

        $inFlight = $this->inFlight($location->id) !== null;
        $lastApplied = $this->lastAppliedAt($location->id);
        $tooSoon = $lastApplied !== null
            && $lastApplied->addHours($this->registry->int('speed.min_hours_between_fixes'))->greaterThan($now);

        $options = [];

        foreach (SpeedFix::cases() as $fix) {
            $options[] = new SpeedFixOption($fix, $this->refusalFor($fix, $location, $tier, $inFlight, $tooSoon));
        }

        return $options;
    }

    /**
     * The next fix to apply to this site, or null when there is none.
     *
     * ⚠️ **`28` §4.1's TABLE ORDER, AND NOT A PRIORITY MODEL.** Ranking the
     * seven by expected effect would need per-site evidence about which of them
     * would help — an image inventory, a script list, a font audit — which is
     * exactly what this platform cannot see (see the class docblock). The
     * document's own order is the honest answer until something can measure a
     * better one.
     */
    public function next(Location $location, ?ActuationTier $tier, ?CarbonImmutable $now = null): ?SpeedFix
    {
        foreach ($this->plan($location, $tier, $now) as $option) {
            if ($option->isApplicable()) {
                return $option->fix;
            }
        }

        return null;
    }

    /**
     * Put one fix on one site: snapshot, open the change set, record the speed
     * row, write.
     *
     * ⛔ **THE SPEED ROW IS WRITTEN BEFORE THE ADAPTER IS ASKED, ON
     * `SiteChanges`' OWN TWO-STEP ARGUMENT.** A write that failed midway — the
     * one where the page may or may not have changed — is the case an owner most
     * needs a record of, and a row inserted afterwards would lose exactly those.
     * The row's `applied_at` stays null until the adapter says it wrote.
     *
     * ⛔ **A SPEED FIX IS NEVER A CREATION.** {@see SiteSnapshot} can honestly
     * answer *"no page at this URL"* and {@see ChangeSet::creating()} exists for
     * that, but making a page faster presupposes a page: an absent snapshot here
     * would have this platform **create** somebody's home page in order to speed
     * it up. Only `Read` opens a speed change set.
     *
     * @param  array<string, mixed>|string|list<string>  $content  What the fix
     *                                                             writes into
     *                                                             its one field.
     */
    public function apply(
        Location $location,
        SpeedFix $fix,
        array|string $content,
        ActuationActor $actor,
        ?CarbonImmutable $now = null,
    ): AdapterOutcome|SpeedFixRefusal {
        $this->assertSameTenant($location);

        $tier = $this->tiers->for($location);

        $refusal = $this->refusalFor(
            $fix,
            $location,
            $tier,
            $this->inFlight($location->id) !== null,
            $this->isTooSoon($location->id, $now ?? CarbonImmutable::now()),
        );

        if ($refusal instanceof SpeedFixRefusal) {
            return $refusal;
        }

        // ⛔ **THE LIVE GATE, ASKED LAST AND ASKED ONCE.** `actuation.enabled`,
        // the tier's own `writesToTheSite()` and the adapter's `health()` are
        // one question with one answer, and `Publishing::canWriteToSite()` is
        // where that answer already lives. A second copy here would be a second
        // place the platform's *"may we write to a stranger's site"* rule could
        // be relaxed by half.
        if (! $this->publishing->canWriteToSite($location)) {
            return SpeedFixRefusal::ActuationOff;
        }

        $url = (string) $location->website_url;

        $snapshot = $this->siteChanges->snapshot($location, $url, [$fix->field()]);

        if ($snapshot->state->permitsAChangeSet() === false || $snapshot->isAbsent()) {
            return SpeedFixRefusal::AdapterCannotWrite;
        }

        $set = new ChangeSet(
            $url,
            $fix->changeType(),
            $tier ?? ActuationTier::T1,
            $snapshot->fields,
            [$fix->field() => $content],
        );

        $change = $this->siteChanges->open($location, $set, $actor);

        $row = SpeedChangeSet::create([
            'location_id' => $location->id,
            'fix_key' => $fix,
            'tier' => $set->tier,
            'change_set_id' => (int) $change->id,
            'status' => SpeedFixStatus::Decided,
            'decided_at' => now(),
            'applied_at' => null,
        ]);

        $outcome = $this->siteChanges->apply($change, $actor);

        if ($outcome->ok) {
            $row->forceFill([
                'status' => SpeedFixStatus::Measuring,
                'applied_at' => now(),
            ])->save();
        }

        return $outcome;
    }

    /**
     * The fix that is on this site now and has not been judged.
     *
     * ⚠️ **THE PARTIAL UNIQUE INDEX SAYS THERE IS AT MOST ONE**, so this reads
     * a state rather than the newest of several.
     */
    public function inFlight(int $locationId): ?SpeedFixRecord
    {
        $row = SpeedChangeSet::query()
            ->where('location_id', $locationId)
            ->whereNotNull('applied_at')
            ->whereNull('result')
            ->orderByDesc('id')
            ->first();

        return $row instanceof SpeedChangeSet ? self::recordOf($row) : null;
    }

    /**
     * Every applied, unjudged speed fix whose seven-day window has closed.
     *
     * ⛔ **THE WINDOW IS `28` §4.3's, NOT `29` §2 RULE 32's, AND THE TWO ARE
     * DIFFERENT QUESTIONS ABOUT THE SAME TABLE.** Rule 32 asks whether a page we
     * wrote earned its place, over 14–30 days, and slice H's sweep answers it.
     * §4.3 asks whether the site got slower, over *"7 days vs the 14-day
     * pre-change baseline"*. `SpeedFix::changeTypes()` is what keeps H's sweep
     * out of these rows.
     *
     * ⛔ **AND EVERY FIX ALREADY JUDGED HARMFUL WHOSE REVERT DID NOT LAND**,
     * which is the second due set and the one that would have been forgotten
     * (5861). `SiteMeasurements::dueForRevert()` cannot carry these: it selects
     * on `site_changes.verdict = regressed`, and this decider writes its verdict
     * to `speed_change_sets` rather than to the three measurement columns —
     * whose shape is content-metrics and would be a lie about a speed fix. So
     * without this arm our own harmful change stays on an unreachable customer's
     * website for ever. **A test found it, and the claim that H's retry covered
     * these rows was written before it was checked** — 314–316, caught by the
     * assertion rather than by the reviewer.
     *
     * ⚠️ **THE BOUNDARY IS A DATE AND NOT A TIMESTAMP, BECAUSE THE JUDGE'S
     * IS.** {@see SpeedDecider::judge()} closes the measured window at the end
     * of a day; a timestamp comparison here would disagree with it by the
     * time of day the fix happened to land, which is how a sweep comes to
     * dispatch a job that always answers `NotDue`.
     *
     * @return list<int>
     */
    public function dueForJudgement(CarbonImmutable $now, int $limit = SiteMeasurements::SWEEP_LIMIT): array
    {
        // ⛔ **THE ROWS THAT HAVE HAD THEIR LAST ATTEMPT ARE CLOSED FIRST, AND
        // THE ORDER IS THE WHOLE OF WHY THIS IS TWO PHASES** (6266). Reaching
        // here still `revert_failed` with the ceiling spent is the first moment
        // *"we have stopped trying"* is a true sentence, because
        // {@see SpeedDecider::putItBack()} writes that status **before** it asks
        // the adapter and corrects it to `rolled_back` only on success. Counting
        // and closing in one breath at write time would tell an owner we had
        // given up on a revert that was about to land.
        $this->closeExhaustedReverts($now);

        $ids = [];
        $after = 0;

        for ($page = 0; $page < SiteMeasurements::SWEEP_SCAN_PAGES && count($ids) < $limit; $page++) {
            /** @var Collection<int, SpeedChangeSet> $due */
            $due = SpeedChangeSet::query()
                ->whereNotNull('applied_at')
                ->where(function ($query) use ($now): void {
                    $query
                        // ⛔ **THE STATUS RATHER THAN `result IS NULL`, WHICH IS THE
                        // SAME SET TODAY AND STOPS BEING SO THE MOMENT A ROW IS
                        // SETTLED WITHOUT AN EVIDENCE DOCUMENT** (5969). A fix the
                        // owner undid from slice J's screen is settled
                        // `rolled_back` and never judged, so it has no `result` —
                        // and a due set keyed on that column would hand it back
                        // every night for ever, which is 5746's card-a-night
                        // failure wearing a worker.
                        ->where(fn ($unjudged) => $unjudged
                            ->where('status', SpeedFixStatus::Measuring)
                            ->whereDate(
                                'applied_at',
                                '<=',
                                $now->subDays(SpeedDecider::MEASURED_WINDOW_DAYS + 1)->toDateString(),
                            ))
                        // ⛔ **THE RETRY ARM, NOW BOUNDED** (6055, 6266). It used
                        // to be a bare `orWhere('status', RevertFailed)`, which
                        // re-dispatched a job about the same page every night for
                        // ever with no counter and no spacing. The two predicates
                        // are the ceiling and the backoff, and neither belongs on
                        // the `Measuring` arm above — that one resolves by itself.
                        ->orWhere(fn ($retry) => $retry
                            ->where('status', SpeedFixStatus::RevertFailed)
                            ->whereNull('revert_attempts_exhausted_at')
                            ->where(fn ($ready) => $ready
                                ->whereNull('revert_attempt_after')
                                ->orWhere('revert_attempt_after', '<=', $now)));
                })
                ->where('id', '>', $after)
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'change_set_id']);

            if ($due->isEmpty()) {
                break;
            }

            $after = (int) $due->last()->id;

            // ⛔ **AND A FIX WHOSE PAGE WE CAN NO LONGER NAME LEAVES THE DUE SET**
            // (5819(a), 5974) — slice H's `dueForMeasurement()` filter, one table
            // along and for its reason: a job a night about a change nobody can act
            // on is 5746's card-a-night failure wearing a worker, and the arm that
            // would run is the one that **writes**.
            //
            // ⚠️ **THE PAGING ABOVE IS BECAUSE OF THIS FILTER** (6266). A single
            // `LIMIT` in front of a PHP-side filter lets a block of rows nobody
            // can act on hold the front of the queue for ever, which is a limit
            // that switches the sweep off with the build green.
            foreach ($due as $row) {
                if (count($ids) >= $limit) {
                    break;
                }

                if ($this->measurements->pageIsIdentifiable($row->change_set_id)) {
                    $ids[] = (int) $row->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Stamp, and tell the owner about, every fix whose revert attempts are spent.
     *
     * ⚠️ **THE CEILING AND THE SCHEDULE ARE `SiteMeasurements`' AND NOT A SECOND
     * COPY.** Two numbers for one rule is how the two sweeps come to disagree
     * about how long this platform keeps asking, and 5861 already records that
     * these rows and that sweep's rows are invisible to one another — which is
     * an argument for sharing the constant, not for owning a second one.
     */
    private function closeExhaustedReverts(CarbonImmutable $now): void
    {
        /** @var Collection<int, SpeedChangeSet> $spent */
        $spent = SpeedChangeSet::query()
            ->where('status', SpeedFixStatus::RevertFailed)
            ->whereNull('revert_attempts_exhausted_at')
            ->where('revert_attempts', '>=', $this->registry->int('sites.revert.attempt_ceiling'))
            ->orderBy('id')
            ->limit(SiteMeasurements::SWEEP_LIMIT)
            ->get(['id', 'change_set_id']);

        foreach ($spent as $row) {
            $row->forceFill([
                'revert_attempt_after' => null,
                'revert_attempts_exhausted_at' => $now,
            ])->save();

            $this->measurements->noteRevertCeilingReached($row->change_set_id);
        }
    }

    /**
     * One speed change set, in the shape the decider needs.
     */
    public function subject(int $id): ?SpeedFixRecord
    {
        $row = SpeedChangeSet::query()->find($id);

        return $row instanceof SpeedChangeSet ? self::recordOf($row) : null;
    }

    /**
     * Write the evidence and the finding.
     *
     * ⛔ **BEFORE THE PAGE IS PUT BACK, NEVER AFTER** — 5816's rule, and the same
     * reason: a revert can fail, and a row reverted with no record of why is an
     * automatic change to a customer's website this platform cannot explain.
     *
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $result
     */
    public function record(int $id, array $baseline, array $result, SpeedFixStatus $status): void
    {
        $row = SpeedChangeSet::query()->find($id);

        if (! $row instanceof SpeedChangeSet) {
            return;
        }

        $row->forceFill([
            'baseline' => $baseline,
            'result' => $result,
            'status' => $status,
        ])->save();
    }

    /**
     * One more failed revert attempt on this fix.
     *
     * ⛔ **A VERB OF ITS OWN, BECAUSE `record()` IS NOT WHERE THE ATTEMPTS
     * HAPPEN AND THE FIRST DRAFT OF THIS PUT IT THERE** (6266). `record()` runs
     * once, on the **first** failure, where {@see SpeedDecider::putItBack()}
     * writes `revert_failed` pessimistically before asking the adapter — and
     * every nightly retry after that goes through
     * `SpeedDecider::retryRevert()`, which records nothing at all. A counter on
     * `record()` would therefore have sat at `1` for ever and never reached the
     * ceiling, **while a test that called `record()` in a loop passed** (411's
     * shape, found by driving the real path).
     *
     * ⛔ **AND THE CLOCK IS THE CALLER'S.** `SpeedDecider::judge()` is handed a
     * `$now` and the sweep is handed a `$now`; a `CarbonImmutable::now()` here
     * would disagree with both whenever a caller is judging as of some other
     * instant, and the row would be invisible to a sweep run at that instant.
     */
    public function countRevertAttempt(int $id, CarbonImmutable $now): void
    {
        $row = SpeedChangeSet::query()->find($id);

        if (! $row instanceof SpeedChangeSet || $row->revert_attempts_exhausted_at !== null) {
            return;
        }

        $attempts = (int) $row->revert_attempts + 1;

        $row->forceFill([
            'revert_attempts' => $attempts,
            'revert_attempt_after' => $now->addHours($this->measurements->revertBackoffHours()[min(
                $attempts,
                count($this->measurements->revertBackoffHours()),
            ) - 1]),
        ])->save();
    }

    /**
     * Say what became of a fix whose revert has now been attempted.
     */
    public function settle(int $id, SpeedFixStatus $status): void
    {
        $row = SpeedChangeSet::query()->find($id);

        if ($row instanceof SpeedChangeSet) {
            $row->forceFill(['status' => $status])->save();
        }
    }

    /**
     * The sentence an owner reads about what we can do for this site's speed.
     *
     * ⛔ **`28` §4.1's *"tells the owner the truth once, simply"*, AND IT IS A
     * SCREEN STATE RATHER THAN A NOTIFICATION — WHICH IS WHAT MAKES *ONCE* TRUE
     * BY CONSTRUCTION.** §4.1 asks for *"one prompt, then a Setup Center item.
     * No repeated nagging"*, and 5542 already settled the shape for the tier
     * offer beside it: a sentence with no button is not a nag, and nothing here
     * sends anything.
     *
     * ⛔ **IT DOES NOT SAY *"measurement + preconnect"* AND MUST NOT UNTIL
     * PRECONNECT EXISTS** (5853, and 314–316). §4.1's pixel column promises
     * both; this platform has no way to know a site's third-party origins, so
     * the preconnect half is unbuilt, and a sentence claiming it would be a
     * promise made on a screen an owner reads.
     */
    public function sentenceFor(Location $location, ?ActuationTier $tier): string
    {
        if ($tier === null) {
            return 'We are still working out what we can do about how fast your website loads.';
        }

        if ($tier === ActuationTier::T1 && $this->canApplyAnything($location, $tier)) {
            return 'We watch how fast your pages load for real visitors, and we speed them up '
                .'one change at a time — measuring each one and putting it back if it did not help.';
        }

        return 'We watch how fast your pages load for real visitors, and we will tell you what is '
            .'slowing them down. Connecting your website fully is what lets us fix it for you.';
    }

    /**
     * Why this fix is not being applied, or null if it can be.
     *
     * ⚠️ **THE ORDER IS CHEAPEST-AND-MOST-GENERAL FIRST**, so the reason a
     * reader is given is the one that would still be true if every other were
     * fixed: a T3 site is told its tier cannot carry this fix rather than that
     * another fix is in flight.
     */
    private function refusalFor(
        SpeedFix $fix,
        Location $location,
        ?ActuationTier $tier,
        bool $inFlight,
        bool $tooSoon,
    ): ?SpeedFixRefusal {
        if ($tier === null) {
            return SpeedFixRefusal::TierUnknown;
        }

        if (! $fix->permittedAt($tier)) {
            return SpeedFixRefusal::TierCannotApply;
        }

        if (! $this->adapter->fieldSupport([$fix->field()])->permits($fix->field())) {
            return SpeedFixRefusal::AdapterCannotWrite;
        }

        if ($this->quarantines->isQuarantined($location->id, $fix->changeType())) {
            return SpeedFixRefusal::Quarantined;
        }

        if ($inFlight) {
            return SpeedFixRefusal::AnotherFixInFlight;
        }

        return $tooSoon ? SpeedFixRefusal::TooSoon : null;
    }

    private function canApplyAnything(Location $location, ?ActuationTier $tier): bool
    {
        foreach ($this->plan($location, $tier) as $option) {
            if ($option->refusal !== SpeedFixRefusal::TierCannotApply
                && $option->refusal !== SpeedFixRefusal::AdapterCannotWrite
                && $option->refusal !== SpeedFixRefusal::TierUnknown) {
                return true;
            }
        }

        return false;
    }

    private function isTooSoon(int $locationId, CarbonImmutable $now): bool
    {
        $last = $this->lastAppliedAt($locationId);

        return $last !== null && $last->addHours($this->registry->int('speed.min_hours_between_fixes'))->greaterThan($now);
    }

    /**
     * When the most recent speed fix reached this site.
     *
     * ⚠️ **APPLIED, NOT DECIDED.** §4.3's ≥48h is about how far apart two
     * changes land on a website; a fix that was chosen and whose write failed
     * never landed, and holding the site closed for two days because of it would
     * be a spacing rule counting attempts rather than changes.
     */
    private function lastAppliedAt(int $locationId): ?CarbonImmutable
    {
        $row = SpeedChangeSet::query()
            ->where('location_id', $locationId)
            ->whereNotNull('applied_at')
            // ⚠️ **BY ID, NOT BY `applied_at`** — `SiteChangeQuarantines::live()`'s
            // reasoning and `ConventionsTest`'s NULLS-FIRST lint. Postgres sorts
            // NULLs **first** on a `DESC`, so every timestamp ordering in this
            // codebase either says `NULLS LAST` out loud or uses the key. Here
            // the two orders cannot disagree: only one fix may be applied and
            // unjudged per site at a time, so applications happen in id order.
            ->orderByDesc('id')
            ->first(['applied_at']);

        return $row instanceof SpeedChangeSet && $row->applied_at !== null
            ? CarbonImmutable::instance($row->applied_at)
            : null;
    }

    private static function recordOf(SpeedChangeSet $row): SpeedFixRecord
    {
        return new SpeedFixRecord(
            (int) $row->id,
            $row->location_id,
            $row->fix_key,
            $row->tier,
            $row->change_set_id,
            $row->status,
            CarbonImmutable::instance($row->decided_at),
            $row->applied_at === null ? null : CarbonImmutable::instance($row->applied_at),
            $row->baseline,
            $row->result,
        );
    }

    /**
     * ⚠️ **`TenantMismatch`'s ARGUMENT AT A THIRD ADDRESS** (5535). A loaded
     * `Location` is self-scoping; an unsaved one is not, and taking its
     * `business_id` as authorization would make the argument the boundary.
     */
    private function assertSameTenant(Location $location): void
    {
        $tenant = Tenancy::idOrFail();

        if ($location->business_id !== $tenant) {
            throw new TenantMismatch($tenant, $location->business_id);
        }
    }
}
