<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\AutopilotActionType;
use App\Enums\DeviceClass;
use App\Enums\SpeedFixStatus;
use App\Enums\SpeedJudgement;
use App\Enums\SpeedTrigger;
use App\Enums\SpeedVerdict;
use App\Enums\WebVital;
use App\Services\ActivityService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Warehouse\ConversionReading;
use App\Services\Warehouse\JsErrorRate;
use App\Services\Warehouse\SiteConversions;
use App\Services\Warehouse\SiteVitals;
use App\Services\Warehouse\VitalReading;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * `28` §4.3's four auto-rollback triggers, verbatim:
 *
 * *"Auto-rollback triggers (any one): p75 LCP or INP worsens >10% over 7 days vs
 * the 14-day pre-change baseline · CLS worsens at all · form-submit or booking
 * conversion rate drops >15% with ≥100 sessions · JS error rate on affected
 * pages doubles. Rollback is automatic, logged to the undo history in plain
 * words … and the fix is quarantined for that site."*
 *
 * ---------------------------------------------------------------------------
 * WHAT IS BOUND RATHER THAN RE-TYPED
 * ---------------------------------------------------------------------------
 * ⛔ **THE >10% IS `SiteVitals::MATERIAL_CHANGE_BASIS_POINTS`, REACHED THROUGH
 * `compare()` RATHER THAN QUOTED** — 5617's explicit instruction to this slice.
 * Two copies of one threshold drift the day either is tuned, and the copy that
 * would have drifted here is the one that reverts a page from a customer's
 * website.
 *
 * ⛔ **THE ≥100 IS `SiteVitals::MINIMUM_SAMPLES`, AND IT IS NOT MOVED** (5769).
 * That constant serves two floors and 5723 ruled on one of them; splitting it
 * is a decision about the measurement and this slice is a client of the
 * measurement. Everything here follows whatever it is.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE SAMPLE FLOOR IS THE WHOLE OF THE PROTECTION, AND CLS IS WHY THAT
 * MATTERS
 * ---------------------------------------------------------------------------
 * Three of the four triggers have a threshold to hide behind. **CLS does not** —
 * §4.3 says *"worsens at all"*, so a single bucket of movement is a regression
 * by the document's own wording. That is only defensible because a window below
 * the floor never reaches the comparison: `SiteVitals::p75()` answers
 * `InsufficientData` and this class requires **both** windows to be
 * measurements before it compares anything. **3789's lesson, transplanted before
 * it happened here**: a containment that fires on one ordinary wobble is a
 * containment sized for a population of one.
 *
 * ---------------------------------------------------------------------------
 * WHAT IS DELIBERATELY NOT A TRIGGER
 * ---------------------------------------------------------------------------
 * ⛔ **TTFB.** The pixel collects it and the mart stores it, and 5762 rules that
 * it is not a Core Web Vital and may never carry a sentence to an owner. §4.3
 * names LCP, INP and CLS; adding a fourth metric would be this platform
 * reverting somebody's website on a signal the specification did not ask for.
 *
 * ⚠️ **AND THE JS-ERROR RATE IS READ SITE-WIDE RATHER THAN PER PAGE**, which
 * §4.3's *"on affected pages"* permits here for one reason: every fix in §4.1 is
 * theme- or asset-layer, so the affected pages **are** the site.
 * `SiteVitals::jsErrorRate()` already takes a page path, so the day a per-page
 * speed fix exists the argument is one parameter rather than a rewrite — and
 * building the branch today would be a branch nothing can reach (256).
 */
final readonly class SpeedDecider
{
    /** `28` §4.3: *"over 7 days"*. */
    public const int MEASURED_WINDOW_DAYS = 7;

    /** `28` §4.3: *"vs the 14-day pre-change baseline"*. */
    public const int BASELINE_DAYS = 14;

    /**
     * §4.3's *"conversion rate drops >15%"*.
     *
     * ⚠️ **READ INCLUSIVELY, ON 5618's PRECEDENT.** That decision settled the
     * same question for the vitals threshold — exactly 10% reads as a regression
     * — and two thresholds in one decider disagreeing about their own boundary
     * is a difference nobody would find except by reading both.
     */
    public const int CONVERSION_DROP_BASIS_POINTS = 1_500;

    /**
     * The revert reason used when a retry cannot find the original.
     *
     * ⚠️ **A FALLBACK RATHER THAN A DEFAULT** — `ChangeMeasurer`'s, verbatim.
     * The only route here is a quarantine an operator released between the
     * failed revert and the retry, and inventing a percentage would be worse
     * than saying less.
     */
    private const string UNRECORDED_REGRESSION = 'Reversed a speed change that was not helping.';

    /**
     * The metrics §4.3 names, in the order it names them.
     *
     * @var list<WebVital>
     */
    private const array TRIGGER_METRICS = [WebVital::Lcp, WebVital::Inp, WebVital::Cls];

    public function __construct(private readonly DefaultsRegistry $registry,
        private SpeedFixes $fixes,
        private SiteVitals $vitals,
        private SiteConversions $conversions,
        private SiteChangeQuarantines $quarantines,
        private SiteMeasurements $measurements,
        private ActivityService $activity,
    ) {}

    /**
     * Judge one applied speed fix.
     *
     * ⚠️ **IDEMPOTENT, BECAUSE THE ROW IS THE STATE.** A second run finds
     * `result` written and answers {@see SpeedJudgement::NotDue}; the windows are
     * anchored on `applied_at`, so a judgement deferred by a paused tenant or a
     * dead queue re-derives the identical verdict from the identical rows.
     */
    public function judge(int $speedChangeSetId, CarbonImmutable $now): SpeedJudgement
    {
        $subject = $this->fixes->subject($speedChangeSetId);

        if ($subject === null || $subject->appliedAt === null) {
            return SpeedJudgement::NotFound;
        }

        // ⛔ **THE RETRY ARM, AND IT MEASURES NOTHING** — `ChangeMeasurer`'s
        // shape, for its reason (5816). A fix already judged harmful whose
        // revert did not land needs the adapter asked again, not the marts read
        // again: the verdict is settled, and re-deriving it from the same
        // anchored windows could only ever produce the same answer.
        //
        // ⚠️ **THIS STAYS FIRST, AND THE GUARD BELOW WAS BRIEFLY PUT ABOVE IT**
        // (5964, and this file's own test found it). A judged row answers
        // `NotDue` for ever — that is what makes `judge()` idempotent — and a
        // guard in front of it would have changed the answer for every fix this
        // platform has ever rolled back, on its second run.
        if ($subject->result !== null) {
            return $subject->status === SpeedFixStatus::RevertFailed
                ? $this->retryRevert($subject, $now)
                : SpeedJudgement::NotDue;
        }

        // ⛔ **THE FIX IS ALREADY OFF THE SITE, AND THE OWNER IS WHO TOOK IT
        // OFF** (5963). Slice J's screen lists **every** applied change set,
        // speed fixes included, and `SiteChanges::revert()` refuses a second
        // revert by raising — so before this guard existed, an owner pressing
        // Undo left this row `measuring` for ever, due every night, with
        // `JudgeSpeedFixJob` failing three times a night against it.
        //
        // ⛔ **AND IT SETTLES RATHER THAN JUDGING.** Measuring a change the
        // customer has already reversed and quarantining the fix on the result
        // would report their decision as our self-correction, which is the
        // collapse `rolled_back_by` exists to prevent (5524) — the same argument
        // `SiteMeasurements::dueForMeasurement()` makes for excluding these rows.
        // `SpeedFixStatus::RolledBack` is true of the site either way; **whose
        // call it was lives on `site_changes.rolled_back_by` and is not
        // duplicated here**, and no evidence document is written because none
        // was earned.
        if (! $this->measurements->isLive($subject->changeSetId)) {
            $this->fixes->settle($subject->id, SpeedFixStatus::RolledBack);

            return SpeedJudgement::RolledBack;
        }

        // ⛔ **THE PAGE THIS FIX IS ON IS NOT ON THE WEBSITE THIS LOCATION NAMES**
        // (5819(a), 5974). A speed change set is opened at exactly
        // `locations.website_url`, and an owner may replace that column while the
        // fix is in flight — after which putting it back writes one site's
        // `before` snapshot onto whichever page answers that path on another.
        // **This is the fix wave reproducing the defect it was fixing** (363–365):
        // the address guard went into `ChangeMeasurer` first, and this decider
        // reverts through the same `SiteMeasurements::revert()` with no guard of
        // its own.
        if (! $this->measurements->pageIsIdentifiable($subject->changeSetId)) {
            return SpeedJudgement::PageNotIdentifiable;
        }

        $measuredFrom = $subject->appliedAt->addDay()->startOfDay();
        $measuredTo = $measuredFrom->addDays(self::MEASURED_WINDOW_DAYS - 1);

        if ($now->startOfDay()->lessThanOrEqualTo($measuredTo)) {
            return SpeedJudgement::NotDue;
        }

        $baselineTo = $subject->appliedAt->subDay()->startOfDay();
        $baselineFrom = $baselineTo->subDays($this->registry->int('speed.baseline_days') - 1);

        $triggers = [];

        $vitals = $this->vitalEvidence($baselineFrom, $baselineTo, $measuredFrom, $measuredTo, $triggers);

        $baselineErrors = $this->vitals->jsErrorRate($baselineFrom, $baselineTo);
        $measuredErrors = $this->vitals->jsErrorRate($measuredFrom, $measuredTo);

        if ($this->errorRateDoubled($baselineErrors, $measuredErrors)) {
            $triggers[] = SpeedTrigger::JsErrorRateDoubled;
        }

        $baselineConversions = $this->conversions->rate($baselineFrom, $baselineTo);
        $measuredConversions = $this->conversions->rate($measuredFrom, $measuredTo);

        if ($this->conversionRateFell($baselineConversions, $measuredConversions)) {
            $triggers[] = SpeedTrigger::ConversionRateFell;
        }

        $baseline = self::document($baselineFrom, $baselineTo, $vitals['baseline'], [
            'state' => $baselineErrors->state->value,
            'pageviews' => $baselineErrors->pageviews,
            'errors' => $baselineErrors->errors,
            'per_thousand' => $baselineErrors->isMeasured() ? $baselineErrors->perThousandPageviews() : 0,
        ], $baselineConversions->toArray());

        $measured = self::document($measuredFrom, $measuredTo, $vitals['measured'], [
            'state' => $measuredErrors->state->value,
            'pageviews' => $measuredErrors->pageviews,
            'errors' => $measuredErrors->errors,
            'per_thousand' => $measuredErrors->isMeasured() ? $measuredErrors->perThousandPageviews() : 0,
        ], $measuredConversions->toArray());

        // ⚠️ **DEDUPED BY VALUE RATHER THAN BY `array_unique()`.** Four device
        // classes can each fire the loading trigger, and a sentence naming it
        // four times is what an owner would read.
        $triggers = self::distinct($triggers);

        if ($triggers === []) {
            // ⛔ **A WINDOW THAT SAID NOTHING IS NOT A WINDOW THAT SAID "NO
            // CHANGE"** (5523). Every signal being unmeasurable is the expected
            // outcome on every deployment that exists (4966), and calling it
            // `kept` would report silence as evidence that the fix was fine.
            $status = self::anythingMeasured($vitals, $baselineErrors->isMeasured() && $measuredErrors->isMeasured(), $baselineConversions->isMeasured() && $measuredConversions->isMeasured())
                ? SpeedFixStatus::Kept
                : SpeedFixStatus::InsufficientData;

            $this->fixes->record($subject->id, $baseline, $measured, $status);

            return $status === SpeedFixStatus::Kept
                ? SpeedJudgement::Kept
                : SpeedJudgement::InsufficientData;
        }

        return $this->putItBack($subject, $baseline, $measured, $triggers, $now);
    }

    /**
     * Ask the site once more for a change it would not take back.
     *
     * ⚠️ **THE REASON IS READ OFF THE QUARANTINE RATHER THAN REBUILT** (5812).
     * Re-deriving the owner-facing sentence would mean re-measuring, which reads
     * the marts a second time and could — if a mart were rebuilt in between —
     * produce a different percentage against the same verdict. The sentence is a
     * fact about the measurement that was made.
     *
     * ⚠️ **NOTHING IS QUARANTINED AGAIN AND NO SECOND ACTION ITEM IS FILED.**
     * The fix is already resting and the owner has already been told once;
     * `SiteChangeQuarantines::quarantine()` would answer false anyway, and the
     * card-a-night failure 5746 records is what an unconditional item here would
     * reproduce.
     */
    private function retryRevert(SpeedFixRecord $subject, CarbonImmutable $now): SpeedJudgement
    {
        // ⛔ **THE OWNER MAY HAVE TAKEN IT OFF BETWEEN TWO NIGHTS OF RETRYING**
        // (5963). `SiteChanges::revert()` refuses a second revert by raising, so
        // without this the job fails rather than settling — and the retry runs
        // once a night for as long as the site refuses, which is a great many
        // chances for a press to land in between.
        if (! $this->measurements->isLive($subject->changeSetId)) {
            $this->fixes->settle($subject->id, SpeedFixStatus::RolledBack);

            return SpeedJudgement::RolledBack;
        }

        // ⛔ **AND THE SAME ADDRESS QUESTION**, because this arm is the one that
        // actually writes: a nightly retry against a website the owner replaced
        // last week is the wrong page every night rather than once.
        if (! $this->measurements->pageIsIdentifiable($subject->changeSetId)) {
            return SpeedJudgement::PageNotIdentifiable;
        }

        $reason = $this->quarantines->reasonFor($subject->locationId, $subject->fix->changeType())
            ?? self::UNRECORDED_REGRESSION;

        $outcome = $this->measurements->revert($subject->changeSetId, ActuationActor::autopilot(), $reason);

        if ($outcome !== null && $outcome->ok) {
            $this->fixes->settle($subject->id, SpeedFixStatus::RolledBack);

            return SpeedJudgement::RolledBack;
        }

        // ⛔ **THE RETRY IS COUNTED HERE OR IT IS NOT COUNTED AT ALL** (6266).
        // This arm records no status — the row is already `revert_failed` — so
        // before this line the nightly retry left no trace, the ceiling in
        // `SpeedFixes::dueForJudgement()` could never be reached, and the arm
        // 6055 describes as *"nightly for ever with no attempt counter"* would
        // have kept both halves of that sentence.
        $this->fixes->countRevertAttempt($subject->id, $now);

        Log::warning('A speed fix measured as harmful still could not be reverted', [
            'business_id' => Tenancy::idOrFail(),
            'location_id' => $subject->locationId,
            'speed_change_set_id' => $subject->id,
            'site_change_id' => $subject->changeSetId,
            'fix' => $subject->fix->value,
            'detail' => $outcome instanceof AdapterOutcome ? $outcome->detail : 'no change set',
        ]);

        return SpeedJudgement::RevertFailed;
    }

    /**
     * Write the evidence, rest the fix, and take the change back off the site.
     *
     * ⛔ **THE QUARANTINE IS NOT CONDITIONAL ON THE REVERT LANDING** — 5816's
     * rule, kept verbatim. What was measured is that this fix made this site
     * worse, which is true whether or not the site will take the change back,
     * and making the rest conditional would leave the speed layer free to try
     * the same fix again on exactly the site it could not undo itself on.
     *
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $measured
     * @param  list<SpeedTrigger>  $triggers
     */
    private function putItBack(
        SpeedFixRecord $subject,
        array $baseline,
        array $measured,
        array $triggers,
        CarbonImmutable $now,
    ): SpeedJudgement {
        $reason = self::reasonFor($subject, $triggers);

        // ⛔ **THE EVIDENCE IS WRITTEN FIRST AND THE PESSIMISTIC STATUS GOES
        // WITH IT** (5816, with one turn added). H's rule is that the finding
        // must survive a website that will not answer; the addition is that the
        // status written alongside it is `revert_failed` — *"we judged this
        // harmful and it is still on their site"* — and is corrected to
        // `rolled_back` only once the adapter says the change came off. A crash
        // between the two therefore leaves a row that overstates the problem
        // rather than one claiming an undo that never happened.
        $this->fixes->record($subject->id, $baseline, $measured, SpeedFixStatus::RevertFailed);

        $newlyQuarantined = $this->quarantines->quarantine(
            $subject->locationId,
            $subject->fix->changeType(),
            $subject->changeSetId,
            ActuationActor::autopilot(),
            $reason,
        );

        // ⛔ **THE SAME `SiteChanges::revert()` THE OWNER'S UNDO SCREEN AND
        // SLICE H's AUTO-REVERT BOTH TAKE.** A third revert path would be a
        // third place the feed can disagree with the website.
        $outcome = $this->measurements->revert($subject->changeSetId, ActuationActor::autopilot(), $reason);

        if ($outcome !== null && $outcome->ok) {
            $this->fixes->settle($subject->id, SpeedFixStatus::RolledBack);

            return SpeedJudgement::RolledBack;
        }

        // ⛔ **ONCE, ON THE FIRST FAILURE, AND THE PREDICATE IS THE QUARANTINE
        // BEING NEW** (5746, and the defect slice H found in its own first
        // draft). `SiteMeasurements::dueForRevert()` re-attempts every night for
        // as long as the site refuses, so an unconditional item is a feed card a
        // night, for ever, about one website.
        if ($newlyQuarantined) {
            $this->activity->record(
                AutopilotActionType::OwnerActionNeeded,
                $subject->locationId,
                [
                    'automation' => 'actuation.speed_fix',
                    'site_change_id' => $subject->changeSetId,
                    'change_type' => $subject->fix->changeType(),
                    'detail' => $outcome instanceof AdapterOutcome
                        ? $outcome->detail
                        : 'the change set could not be loaded',
                ],
            );
        }

        // ⛔ **THE FIRST ATTEMPT IS ATTEMPT ONE** (6266), so it is counted here
        // as well as in `retryRevert()`. Counting only the retries would make
        // the ceiling one attempt longer than it says it is.
        $this->fixes->countRevertAttempt($subject->id, $now);

        // ⚠️ **THE OPERATOR'S HALF IS UNCONDITIONAL**, where the owner's is not:
        // *"still failing"* is the thing an operator needs nightly, and `29`
        // §2.4 permits the tenant in log context and nothing about a person.
        Log::warning('A speed fix measured as harmful could not be reverted', [
            'business_id' => Tenancy::idOrFail(),
            'location_id' => $subject->locationId,
            'speed_change_set_id' => $subject->id,
            'site_change_id' => $subject->changeSetId,
            'fix' => $subject->fix->value,
            'detail' => $outcome instanceof AdapterOutcome ? $outcome->detail : 'no change set',
        ]);

        return SpeedJudgement::RevertFailed;
    }

    /**
     * The vitals half of both evidence documents, and the two triggers that come
     * out of it.
     *
     * ⛔ **LCP AND INP GO THROUGH `SiteVitals::compare()` AND CLS DOES NOT, AND
     * THAT ASYMMETRY IS §4.3's RATHER THAN AN INCONSISTENCY.** `compare()` is
     * built around a >10% threshold, which is the wrong question for a metric
     * the document says worsens *"at all"* — running CLS through it would
     * silently grant layout shift a 10% allowance nobody wrote down, and the
     * mutant that introduced it would look like a tidy-up.
     *
     * @param  list<SpeedTrigger>  $triggers
     * @return array{baseline: array<string, array<string, array<string, mixed>>>, measured: array<string, array<string, array<string, mixed>>>}
     */
    private function vitalEvidence(
        CarbonImmutable $baselineFrom,
        CarbonImmutable $baselineTo,
        CarbonImmutable $measuredFrom,
        CarbonImmutable $measuredTo,
        array &$triggers,
    ): array {
        $baseline = [];
        $measured = [];

        foreach (self::TRIGGER_METRICS as $metric) {
            foreach (DeviceClass::cases() as $device) {
                $before = $this->vitals->p75($metric, $device, $baselineFrom, $baselineTo);
                $after = $this->vitals->p75($metric, $device, $measuredFrom, $measuredTo);

                $baseline[$metric->value][$device->value] = self::reading($before);
                $measured[$metric->value][$device->value] = self::reading($after);

                if (! $before->isMeasured() || ! $after->isMeasured()) {
                    continue;
                }

                if ($metric === WebVital::Cls) {
                    if ($after->p75() > $before->p75()) {
                        $triggers[] = SpeedTrigger::LayoutShiftWorsened;
                    }

                    continue;
                }

                $comparison = $this->vitals->compare(
                    $metric,
                    $device,
                    $baselineFrom,
                    $baselineTo,
                    $measuredFrom,
                    $measuredTo,
                );

                if ($comparison->verdict === SpeedVerdict::Worsened) {
                    $triggers[] = SpeedTrigger::LoadingOrResponsivenessWorsened;
                }
            }
        }

        return ['baseline' => $baseline, 'measured' => $measured];
    }

    /**
     * §4.3's fourth trigger.
     *
     * ⚠️ **ZERO-TO-ANYTHING IS A REGRESSION, AND THAT IS `SiteVitals::compare()`'s
     * OWN ANSWER TO A ZERO BASELINE REACHED FOR DELIBERATELY** (5859). Nothing
     * doubles from zero, so the arm had to be ruled on rather than inherited —
     * and errors appearing where there were none is the exact failure §4.2 calls
     * *"the only risky fix"*. The floor is what stops one unlucky visitor
     * firing it: at `SiteVitals::MINIMUM_SAMPLES` pageviews a single error is a
     * rate, not an anecdote.
     */
    private function errorRateDoubled(JsErrorRate $baseline, JsErrorRate $measured): bool
    {
        if (! $baseline->isMeasured() || ! $measured->isMeasured()) {
            return false;
        }

        $before = $baseline->perThousandPageviews();
        $after = $measured->perThousandPageviews();

        return $before === 0 ? $after > 0 : $after >= $before * 2;
    }

    /**
     * §4.3's third trigger.
     *
     * ⛔ **A ZERO BASELINE CANNOT FIRE THIS, WHICH IS THE MIRROR IMAGE OF THE
     * ERROR ARM AND IS `BUILD-PLAN` §2.11.5 CONFLICT 6 SATISFIED BY ARITHMETIC
     * RATHER THAN BY A FLAG.** A conversion rate falling from nothing is not a
     * fall, so a tenant whose site records no conversions — because none are
     * tracked, or because none happened — is never reverted on a signal they do
     * not have. *"A trigger evaluated over a signal that cannot exist is 256's
     * vacuous pass wearing a threshold."*
     */
    private function conversionRateFell(ConversionReading $baseline, ConversionReading $measured): bool
    {
        if (! $baseline->isMeasured() || ! $measured->isMeasured() || $baseline->ratePerTenThousand === 0) {
            return false;
        }

        $fall = intdiv(
            ($baseline->ratePerTenThousand - $measured->ratePerTenThousand) * 10_000,
            $baseline->ratePerTenThousand,
        );

        return $fall >= self::CONVERSION_DROP_BASIS_POINTS;
    }

    /**
     * @param  list<SpeedTrigger>  $triggers
     * @return list<SpeedTrigger>
     */
    private static function distinct(array $triggers): array
    {
        $seen = [];

        foreach ($triggers as $trigger) {
            $seen[$trigger->value] = $trigger;
        }

        return array_values($seen);
    }

    /**
     * Did any signal at all produce a comparison?
     *
     * @param  array{baseline: array<string, array<string, array<string, mixed>>>, measured: array<string, array<string, array<string, mixed>>>}  $vitals
     */
    private static function anythingMeasured(array $vitals, bool $errors, bool $conversions): bool
    {
        if ($errors || $conversions) {
            return true;
        }

        foreach ($vitals['baseline'] as $metric => $devices) {
            foreach ($devices as $device => $reading) {
                if (($reading['state'] ?? null) === 'measured'
                    && (($vitals['measured'][$metric][$device]['state'] ?? null) === 'measured')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $vitals
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $conversions
     * @return array<string, mixed>
     */
    private static function document(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $vitals,
        array $errors,
        array $conversions,
    ): array {
        return [
            // ⚠️ **VERSIONED, ON 5818's PRECEDENT.** A later slice adding a
            // signal must not make an older row unreadable or, worse, silently
            // misread.
            'v' => 1,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => (int) $from->diffInDays($to) + 1,
            'vitals' => $vitals,
            'js_errors' => $errors,
            'conversions' => $conversions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reading(VitalReading $reading): array
    {
        return [
            'state' => $reading->state->value,
            'samples' => $reading->samples,
            'p75' => $reading->isMeasured() ? $reading->p75() : null,
        ];
    }

    /**
     * The sentence an owner reads — `28` §4.3's own words, with what actually
     * moved named after them.
     *
     * ⚠️ **PLAIN WORDS AND NO JARGON**, `29` §2 rule 47: what happened to their
     * website, never how this system is built. §4.3 gives the sentence — *"We
     * reversed a speed change that wasn't helping"* — and 3787's rule is that a
     * finding with no denominator beside it is what makes a defect hard to see,
     * so the window is named too.
     *
     * @param  list<SpeedTrigger>  $triggers
     */
    private static function reasonFor(SpeedFixRecord $subject, array $triggers): string
    {
        $said = array_map(static fn (SpeedTrigger $trigger): string => match ($trigger) {
            SpeedTrigger::LoadingOrResponsivenessWorsened => 'your pages got slower to load',
            SpeedTrigger::LayoutShiftWorsened => 'the page moved about more while it loaded',
            SpeedTrigger::ConversionRateFell => 'fewer visitors got in touch',
            SpeedTrigger::JsErrorRateDoubled => 'more visitors hit errors on the site',
        }, $triggers);

        return 'Reversed a speed change that was not helping. We had '.$subject->fix->label()
            .', and over the week that followed, '.implode(' and ', $said)
            .' compared with the fortnight before.';
    }
}
