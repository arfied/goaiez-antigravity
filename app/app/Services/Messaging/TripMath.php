<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Console\Commands\WatchPlatformComplaintRate;
use App\Enums\SignalState;
use App\Services\Sms\NumberHealthService;
use Carbon\CarbonImmutable;

/**
 * How a complaint-rate stop is actually worked out — T176 `P9`'s *"admin display
 * of the trip math (rate, floor, current standing per tenant + platform)"*.
 *
 * ## What was missing, given that both figures were already on the screen
 *
 * `SendingControls` showed the configured rate and the floor, and
 * {@see RateReading} showed the standing. What no screen showed is the step
 * between them: **at this volume, how many complaints is a stop** — and its
 * consequence, **at this threshold, how large must the floor be for one ordinary
 * opt-out not to be a stop on its own.** An operator setting a threshold could
 * see two numbers and could not see what they did together, so the only way to
 * find out was to be stopped by them.
 *
 * ⛔ **AND NEITHER SEEDED PAIR SURVIVES THAT ARITHMETIC TODAY** (4373). Both
 * floors are **50**, which is the figure this display was built to make
 * arguable:
 *
 *   - **The platform.** 100bp (1%) over 50 delivered. **One** complaint in 50 is
 *     200bp, at or over the threshold, so a single STOP in a quiet 24 hours
 *     halts every message this company sends. The floor 1% needs is 202.
 *   - **A tenant.** 300bp (3%) over 50 delivered. **Two** complaints in 50 is
 *     400bp, so two people changing their minds pauses a business. The floor 3%
 *     needs is 67 — which is 3986's own figure for the number-quarantine rail,
 *     found on the same arithmetic and fixed there and not here.
 *
 * ⚠️ **THIS IS NOT A HYPOTHETICAL ABOUT A FUTURE SETTING** — it is what is
 * seeded, and it is the first thing this display makes visible. It is surfaced
 * rather than corrected here, because the figures are the owner's (2409) and
 * P9's seeding half was deliberately left to them.
 *
 * ## No second rate formula, ever
 *
 * ⚠️ **THIS CLASS DIVIDES NOTHING.** `$observedBp` arrives from
 * {@see SendingRates::complaintRateBp()} or {@see PlatformRateSample::basisPoints()}
 * — whichever the trip for that subject actually reads — because a screen that
 * recomputed the rate would be 2402's second source of truth, and a screen that
 * disagreed with the guard about a tenant's rate is worse than one that shows
 * nothing. What is computed here is only what nothing else computes: the
 * complaint count that crosses a threshold, and the volume a threshold needs.
 *
 * ## The two derivations, and why they are inverses of each other
 *
 * A trip fires when `round(complaints · 10000 / delivered) >= threshold`, which
 * is {@see SendingRates}'s formula and {@see SendingGuard}'s comparison. Reading
 * it in either direction gives one of the two figures here:
 *
 *   - **Fix the volume, solve for complaints.** `round(x) >= t` exactly when
 *     `x >= t - 0.5`, so the smallest tripping count is
 *     `ceil(delivered · (2t − 1) / 20000)`. This is *"how many more before we
 *     stop"* and it is the number an operator watching a live campaign wants.
 *   - **Fix the complaints at `k`, solve for volume.** The smallest sample on
 *     which `k` complaints are strictly under the threshold is
 *     `floor(20000·k / (2t − 1)) + 1` — 3986's derivation, in basis points and
 *     **with the rounding**, which the straight percent-to-bp port drops. This
 *     is *"is this floor big enough to mean anything"* and it is the number
 *     whoever sets a threshold needs and cannot get anywhere else.
 *
 * ⚠️ **THE SECOND IS 3986'S ARITHMETIC AND NOT A NEW ONE**, applied to a
 * different trigger. {@see NumberHealthService} derives the same floor for the
 * per-number STOP quarantine and its `minDeliveredForStopTrigger()` carries the
 * full argument for `k = 2`. ⛔ **The constant is duplicated rather than shared,
 * deliberately**: these are two triggers with two thresholds and two owners, and
 * a single shared `k` would mean tightening one containment silently retuned the
 * other. What is shared is the reasoning, which is why that method is named here
 * for anybody about to change either.
 */
final readonly class TripMath
{
    /**
     * How many complaints the trip must be arithmetically unable to fire on,
     * whatever the threshold is set to.
     *
     * ⛔ **NOT A REGISTRY KEY, FOR 3986'S REASON.** It is a property of the
     * arithmetic rather than a policy: an Ops-editable *"how few complaints may
     * stop a tenant"* has no setting anybody would want other than the one
     * {@see self::floorThatSurvivesNoise()} already computes from the threshold.
     * `NumberHealthService::minDeliveredForStopTrigger()` carries the argument
     * for why this is 2 and not 1, 3 or 4 — each step buys less and costs more,
     * and the residual is not meant to be driven to zero by a floor.
     */
    private const int NOISE_FLOOR_K = 2;

    /**
     * @param  string  $subject  what a stop would stop, in the words the screen
     *                           uses — this is rendered, so it is outcome
     *                           language and never a class or key name
     * @param  ?int  $thresholdBp  null when nobody has configured one; a
     *                             non-positive figure disables the trip and is
     *                             normalised to null by the constructors
     * @param  ?int  $delivered  null when the standing is not measurable where
     *                           this is being rendered — never `0` standing in
     *                           for "we did not look", which is
     *                           {@see RateReading}'s rule and the same trap
     * @param  ?int  $observedBp  the rate the trip itself reads, never one
     *                            recomputed here — and **null whenever the
     *                            denominator is zero**, which is
     *                            {@see RateReading::ceiling()}'s own rule
     *                            (4484–4487)
     * @param  ?int  $sent  how many messages were handed to a transport in the
     *                      same window, which is the **only** thing that tells
     *                      an empty `$delivered` meaning *"nothing has been
     *                      sent"* from one meaning *"nothing has been reported
     *                      back"* (7480–7486); null wherever `$delivered` is
     * @param  ?int  $failed  carried for one reason and deliberately never
     *                        rendered as a rate (6365): with `$delivered` it is
     *                        {@see SendingRates::outcomesReported()}, and this
     *                        panel must apply the identical arithmetic the
     *                        containment does or the two disagree about one
     *                        tenant — a carrier failing everything is loud, not
     *                        silent
     */
    private function __construct(
        public string $subject,
        public ?int $thresholdBp,
        public int $floorDelivered,
        public ?int $delivered,
        public ?int $complaints,
        public ?int $observedBp,
        public int $windowHours,
        public ?CarbonImmutable $measuredAt,
        public ?int $sent,
        public ?int $failed,
    ) {}

    /**
     * The arithmetic for a subject whose current standing is in front of us.
     *
     * The per-tenant case, and the platform case whenever
     * {@see WatchPlatformComplaintRate} has reported a sample.
     *
     * ⛔ **ZERO DELIVERED IS NOT A RATE OF ZERO, AND THIS CONSTRUCTOR SAID IT WAS
     * UNTIL 2026-08-16** (4484–4487). `$observedBp` arrives as `0` from
     * {@see SendingRates::complaintRateBp()} whenever the denominator is empty —
     * *"zero delivered is zero, not a division by zero and not 100%"* — so the
     * panel rendered a confident **0.00%** under *"Right now"* for a business
     * that had delivered nothing, three lines under a comment reading *"a dash,
     * never 0.00% — an unread counter is not a clean one"*. ⚠️ **And the
     * Complaints card a few hundred pixels above it showed a dash for the same
     * business at the same moment**, because {@see RateReading::ceiling()} nulls
     * its rate on an empty denominator: two answers to one question on one
     * screen, which is 3418's shape.
     *
     * ⚠️ **THE NULLING IS DONE HERE RATHER THAN IN `standingFigure()`**, exactly
     * as `RateReading` does it at construction, so every reader of `$observedBp`
     * inherits the honesty instead of one renderer remembering it.
     * {@see self::isMeasured()} is deliberately left alone: *"nothing has been
     * delivered"* is still a measurement, and it is the one that produces
     * *"0 delivered so far, so 50 more before anything can stop"* — the sentence
     * an operator on day one actually needs.
     */
    public static function measured(
        string $subject,
        ?int $thresholdBp,
        int $floorDelivered,
        int $delivered,
        int $complaints,
        int $observedBp,
        int $windowHours,
        int $sent,
        int $failed,
        ?CarbonImmutable $measuredAt = null,
    ): self {
        $delivered = max(0, $delivered);

        return new self(
            subject: $subject,
            thresholdBp: $thresholdBp !== null && $thresholdBp > 0 ? $thresholdBp : null,
            floorDelivered: max(0, $floorDelivered),
            delivered: $delivered,
            complaints: max(0, $complaints),
            observedBp: $delivered > 0 ? $observedBp : null,
            windowHours: $windowHours,
            measuredAt: $measuredAt,
            sent: max(0, $sent),
            failed: max(0, $failed),
        );
    }

    /**
     * The arithmetic for a subject whose standing cannot be read from here.
     *
     * ⚠️ **THE PLATFORM'S CASE WHENEVER THE SWEEP HAS NOT REPORTED**, and the
     * honest one: `sending_health_windows` is `FORCE ROW LEVEL SECURITY`, so a
     * request path that summed it across tenants would read **zero** rather than
     * fail — a healthy-looking platform that is simply unreadable.
     * {@see PlatformComplaintRate} refuses that walk outside a console command
     * for exactly this reason, and this constructor is what lets the screen say
     * so instead of rendering the zero.
     *
     * The configured figures and both derivations are still answerable, because
     * neither depends on a measurement.
     */
    public static function unmeasured(
        string $subject,
        ?int $thresholdBp,
        int $floorDelivered,
        int $windowHours,
    ): self {
        return new self(
            subject: $subject,
            thresholdBp: $thresholdBp !== null && $thresholdBp > 0 ? $thresholdBp : null,
            floorDelivered: max(0, $floorDelivered),
            delivered: null,
            complaints: null,
            observedBp: null,
            windowHours: $windowHours,
            measuredAt: null,
            // ⚠️ **NULL RATHER THAN ZERO, ON THIS CLASS'S OWN RULE.** A `0`
            // here would say *"nothing was handed to a transport"*, which is
            // the confident measurement 4484–4487 removed from `$observedBp`.
            // Nothing was read at all.
            sent: null,
            failed: null,
        );
    }

    /**
     * Will anything stop by itself at all?
     *
     * ⚠️ **BOTH FIGURES, AND EITHER AT ZERO MEANS NO.** That is the rule
     * {@see PlatformRateSample::trips()} and {@see SendingGuard} both apply —
     * *"the two are one setting in two boxes"* — restated here rather than
     * re-decided, so the screen and the trip cannot answer differently.
     */
    public function isArmed(): bool
    {
        return $this->thresholdBp !== null && $this->floorDelivered > 0;
    }

    /**
     * Is there a standing to show, as opposed to a zero standing in for one?
     */
    public function isMeasured(): bool
    {
        return $this->delivered !== null;
    }

    /**
     * Is there enough traffic for the threshold to be able to fire?
     */
    public function floorIsMet(): bool
    {
        return $this->isMeasured() && (int) $this->delivered >= $this->floorDelivered;
    }

    /**
     * How much more has to be delivered before a stop is even possible.
     */
    public function deliveredToFloor(): ?int
    {
        if (! $this->isMeasured()) {
            return null;
        }

        return max(0, $this->floorDelivered - (int) $this->delivered);
    }

    /**
     * Is the floor unmet because nothing was sent, or because nothing came back?
     *
     * ⛔ **THIS PANEL EXISTS TO SAY WHETHER A CONTAINMENT CAN FIRE, AND IT GAVE
     * THE SAME REASSURING ANSWER TO BOTH — VERIFIED ON 2026-08-22 (7480).** With
     * 4,000 messages handed to a carrier, 200 people having replied STOP, and
     * not one receipt placeable, this panel rendered:
     *
     *   > **Not enough sending to stop anything** · *"0 delivered so far, so 50
     *   > more before anything can stop."*
     *
     * Every word of that was true of the column it read and every word of it
     * was the opposite of the situation. **The operator is told to wait for
     * traffic that has already gone out.**
     *
     * ⚠️ **{@see SendingRates::trafficWithoutOutcomes()} IS THE PREDICATE AND
     * THIS IS NOT A SECOND ONE** — the same rule, restated over this object's
     * own fields for the same reason {@see self::isArmed()} restates the trip's:
     * a screen and a containment must not disagree about a tenant. ⚠️ The floor
     * is `floorDelivered`, which is exactly the figure that predicate takes.
     *
     * False whenever nothing was measured, whenever anything at all came back,
     * and whenever less has gone out than the trip's own floor — under which
     * silence is an ordinary campaign whose receipts are still in flight.
     */
    public function reportingHasGoneSilent(): bool
    {
        if (! $this->isMeasured() || $this->sent === null || $this->failed === null) {
            return false;
        }

        if ($this->floorDelivered <= 0) {
            return false;
        }

        $reported = (int) $this->delivered + (int) $this->failed;

        return $this->sent >= $this->floorDelivered && $reported === 0;
    }

    /**
     * The smallest number of complaints that stops this subject, at the volume
     * it has actually delivered.
     *
     * ⚠️ **AT THE VOLUME NOW, WHICH IS WHY IT MOVES AS A CAMPAIGN RUNS.** More
     * delivered messages means more complaints are needed, so this figure rises
     * through a healthy send and is the one an operator watches. Null when
     * nothing is armed, when there is no measurement, or when the floor has not
     * been reached — in the last case no number of complaints trips anything,
     * which is a different answer from a large one.
     */
    public function complaintsToTrip(): ?int
    {
        if (! $this->isArmed() || ! $this->floorIsMet()) {
            return null;
        }

        return $this->smallestTrippingCount((int) $this->delivered);
    }

    /**
     * How many more complaints this subject can take before it stops.
     */
    public function headroom(): ?int
    {
        $toTrip = $this->complaintsToTrip();

        if ($toTrip === null) {
            return null;
        }

        return max(0, $toTrip - (int) $this->complaints);
    }

    /**
     * Has the threshold been crossed, on the same terms the trip applies?
     *
     * ⚠️ **`$observedBp` CANNOT BE NULL HERE AND THERE IS NO GUARD SAYING SO**,
     * on 4394's rule: {@see self::isArmed()} requires a floor above zero and
     * {@see self::floorIsMet()} requires the delivered count to have reached it,
     * so a subject that gets past both lines has delivered at least one message
     * and {@see self::measured()} kept its rate. A guard nothing can drive is one
     * the next reader reasons from wrongly.
     */
    public function trips(): bool
    {
        if (! $this->isArmed() || ! $this->floorIsMet()) {
            return false;
        }

        return (int) $this->observedBp >= (int) $this->thresholdBp;
    }

    /**
     * The smallest floor at which {@see self::NOISE_FLOOR_K} complaints are
     * strictly under this threshold — 3986's derivation, in basis points.
     *
     * ⚠️ **THIS IS THE NUMBER THAT MAKES A TIGHTER RATE HONEST OR DISHONEST.** A
     * threshold is only a measurement above this volume; below it the trigger is
     * firing on arithmetic. At 300bp it is 67; at 100bp it is 202; at 20bp it is
     * 1,026; at 10bp it is 2,106 — so a rate thirty times tighter needs a floor
     * roughly thirty times larger, and setting one without the other converts a
     * containment into a coin toss.
     *
     * ⛔ **THE BASIS-POINT FORM IS NOT `floor(10000·k / t) + 1`, AND WRITING IT
     * THAT WAY IS OFF BY ONE IN A WAY THAT READS PERFECTLY** (4372). That is the
     * straight port of 3986's percent formula and it was what this method
     * returned until a test drove the answer back through
     * {@see SendingRates::complaintRateBp()}: at 100bp it gives 201, and two
     * complaints in 201 delivered is 99.50…bp, which **rounds to 100** and trips.
     * `SendingRates` rounds to whole basis points and the guard compares with
     * `>=`, so the rounding eats the margin the derivation was buying. Solving
     * `round(k·10000/d) < t` gives `d > 20000k / (2t − 1)`, which is the same
     * `2t − 1` that {@see self::smallestTrippingCount()} uses — the two are one
     * inversion read in opposite directions, and they now agree.
     *
     * ⚠️ **THIS IS ALSO WHY `NOISE_FLOOR_K` IS DUPLICATED RATHER THAN SHARED
     * WITH `NumberHealthService`.** That trigger compares floats against a
     * percentage and never rounds to an integer rate, so the percent form is
     * correct there and would be wrong here. Same reasoning, different
     * arithmetic; one shared helper would have to be wrong for one of them.
     */
    public function floorThatSurvivesNoise(): ?int
    {
        if ($this->thresholdBp === null) {
            return null;
        }

        return (int) floor(20_000 * self::NOISE_FLOOR_K / (2 * $this->thresholdBp - 1)) + 1;
    }

    /**
     * Is the configured floor large enough for the configured rate to mean
     * anything?
     */
    public function floorSurvivesNoise(): bool
    {
        $needed = $this->floorThatSurvivesNoise();

        return $needed !== null && $this->floorDelivered >= $needed;
    }

    /**
     * How few complaints trip at the configured floor — the figure that says
     * what an unsafe pairing actually costs.
     *
     * ⚠️ **COMPUTED AT THE FLOOR RATHER THAN AT TODAY'S VOLUME**, and that is
     * the point: the floor is the smallest sample the threshold is ever applied
     * to, so it is where the trigger is most easily fired by chance. Null when
     * nothing is armed.
     */
    public function complaintsToTripAtFloor(): ?int
    {
        if (! $this->isArmed()) {
            return null;
        }

        return $this->smallestTrippingCount($this->floorDelivered);
    }

    /**
     * The threshold as a percentage, or a plain statement that there is not one.
     *
     * ⚠️ **TWO DECIMAL PLACES, NOT ONE.** `RateReading` shows one, which is
     * right for a rate somebody is reading at a glance; it renders R17's
     * proposed 10bp as "0.1%" and 20bp as "0.2%", and this panel exists to be
     * argued from. A threshold display that cannot distinguish 0.05% from 0.10%
     * would hide the whole question P9 was raised to ask.
     */
    public function thresholdFigure(): string
    {
        if ($this->thresholdBp === null) {
            return 'none set';
        }

        return number_format($this->thresholdBp / 100, 2).'%';
    }

    /**
     * The current standing, or a dash.
     *
     * ⛔ **A DASH RATHER THAN "0.00%", WHICH IS {@see RateReading}'S RULE.** An
     * em dash cannot be misread as a measurement; a zero can, and reads as the
     * best possible one.
     *
     * ⚠️ **INCLUDING THE ZERO-DELIVERED CASE, WHICH THIS METHOD USED TO PRINT AS
     * A CONFIDENT `0.00%`** (4484–4487). It is handled where `RateReading`
     * handles it — at construction, in {@see self::measured()} — so the check
     * here is still the single `$observedBp === null` and there is no second
     * spelling of "empty denominator" to keep in step.
     */
    public function standingFigure(): string
    {
        if ($this->observedBp === null || ! $this->isMeasured()) {
            return '—';
        }

        return number_format($this->observedBp / 100, 2).'%';
    }

    /**
     * The signal, which is `Unknown` for every state that is not a measurement.
     *
     * ⛔ **THE PILL REPORTS THE SUBJECT AND NOT THE CONFIGURATION, AND IT
     * REPORTED THE CONFIGURATION UNTIL 2026-08-16** (4488–4491).
     * {@see self::floorSurvivesNoise()} was tested *before* the floor and the
     * measurement, and it is a property of the **pair of settings** rather than
     * of the subject in front of you — so a tenant with 1,000 delivered, 12
     * complaints and 18 complaints of headroom was labelled *"Stops on too
     * little to judge"*. ⚠️ **And because neither seeded pair passes that check
     * today** (4373), **every** tenant and the platform panel showed the
     * identical amber pill, carrying no per-subject information at all: 511's
     * failure — a warning tuned until it stops distinguishing anything — inside
     * the one screen a containment is read from. The per-subject states the
     * panel exists to show were unreachable in practice.
     *
     * ⚠️ **THE CONFIGURATION WARNING IS NOT LOST — IT HAS ITS OWN SURFACE.**
     * {@see self::noiseWarning()} renders an attention card in the same panel,
     * with the arithmetic and the two figures named, which is where a setting
     * that needs changing belongs. It is a sentence an operator can act on
     * rather than a colour on a badge.
     *
     * ⚠️ **`Attention` NOW MEANS THE FLOOR ACTUALLY GOVERNS THIS SUBJECT** —
     * armed, measured, and under the volume at which anything can stop. That is
     * a fact about the tenant, it changes as they send, and it is what the
     * amber was always meant to say. `Alert` still takes precedence, so a live
     * breach is never masked by either.
     */
    public function state(): SignalState
    {
        if (! $this->isArmed()) {
            return SignalState::Unknown;
        }

        if ($this->trips()) {
            return SignalState::Alert;
        }

        if (! $this->isMeasured()) {
            return SignalState::Unknown;
        }

        // ⚠️ **`Attention` FOR BOTH, AND THAT IS DELIBERATE RATHER THAN LAZY**
        // (7480). Silent reporting is a platform fault and an unmet floor is an
        // ordinary early state, but a colour is not what tells them apart —
        // `22`'s rule is that colour is never the signal, and the label and the
        // sentence beside it are what carry the difference. Promoting this to
        // `Alert` would put a red pill on a tenant who has done nothing wrong
        // and would sit beside a genuine breach in the same colour.
        return $this->floorIsMet() ? SignalState::Ok : SignalState::Attention;
    }

    /**
     * The word beside the colour — colour is never the signal (`22`, `29` §5.5).
     *
     * ⚠️ **"STOPS ON TOO LITTLE TO JUDGE" IS GONE FROM HERE AND SAID IN THE
     * ATTENTION CARD INSTEAD** — see {@see self::state()} for why a label about
     * the settings could never be a label about the subject.
     */
    public function stateLabel(): string
    {
        return match (true) {
            ! $this->isArmed() => 'Will not stop by itself',
            $this->trips() => 'Past the stopping point',
            ! $this->isMeasured() => 'Not measured here',
            // ⛔ **BEFORE THE FLOOR, BECAUSE BOTH ARE TRUE AND ONLY ONE IS THE
            // ANSWER** (7480). A tenant whose receipts have gone silent has an
            // unmet floor by construction, so the order of these two arms is
            // the whole correction: *"not enough sending"* is what this panel
            // said with 4,000 messages on the wire.
            $this->reportingHasGoneSilent() => 'Nothing is being reported back',
            ! $this->floorIsMet() => 'Not enough sending to stop anything',
            default => 'Under the stopping point',
        };
    }

    /**
     * The arithmetic, in a sentence — what these two figures do together.
     *
     * Outcome language (`22`) and **no personal data**: counts and percentages,
     * never a business name, a number or a recipient.
     */
    public function arithmetic(): string
    {
        if (! $this->isArmed()) {
            return sprintf(
                'Nothing stops %s by itself. A rate and a smallest sample are both needed and one of '
                .'them is not set, so no arithmetic runs at all.',
                $this->subject,
            );
        }

        $opening = sprintf(
            'Once %s delivered messages have gone out in %d hours, %s stops at %s of them — which is '
            .'%s at that smallest sample.',
            number_format($this->floorDelivered),
            $this->windowHours,
            $this->subject,
            $this->thresholdFigure(),
            $this->complaintsAtFloorPhrase(),
        );

        if (! $this->isMeasured()) {
            return $opening;
        }

        if ($this->reportingHasGoneSilent()) {
            // ⛔ **THE SENTENCE THIS ARM REPLACES WAS TRUE OF THE COLUMN AND THE
            // OPPOSITE OF THE SITUATION** (7480). It read *"0 delivered so far,
            // so 50 more before anything can stop"* — to an operator whose
            // carrier had taken 4,000 messages and reported on none of them.
            // **It told them to wait for traffic that had already gone out.**
            return $opening.sprintf(
                ' %s handed to a carrier or mail transport in that window and not one outcome '
                .'reported back, so nothing can stop %s at all. Until something is reported, this '
                .'is not a quiet period — it is a stop that cannot happen.',
                number_format((int) $this->sent),
                $this->subject,
            );
        }

        if (! $this->floorIsMet()) {
            return $opening.sprintf(
                ' %s delivered so far, so %s more before anything can stop.',
                number_format((int) $this->delivered),
                number_format((int) $this->deliveredToFloor()),
            );
        }

        return $opening.sprintf(
            ' %s delivered so far and %s counted, so %s.',
            number_format((int) $this->delivered),
            $this->complaintsCountedPhrase(),
            $this->headroomPhrase(),
        );
    }

    /**
     * Whether the pair of figures is safe, said plainly — the thing nothing else
     * on any screen answers.
     *
     * Null when there is nothing to warn about, so a caller renders this only
     * when it has something to say rather than printing reassurance.
     */
    public function noiseWarning(): ?string
    {
        if (! $this->isArmed() || $this->floorSurvivesNoise()) {
            return null;
        }

        $count = (int) $this->complaintsToTripAtFloor();

        return sprintf(
            'These two figures do not hold together. At %s, the smallest sample has to be at least %s '
            .'delivered messages before two ordinary complaints are under the line — this one is %s, '
            .'where %s already %s %s. Raise the sample or loosen the rate.',
            $this->thresholdFigure(),
            number_format((int) $this->floorThatSurvivesNoise()),
            number_format($this->floorDelivered),
            $this->complaintsAtFloorPhrase(),
            // The verb has to agree with the count or the one sentence an
            // operator is meant to act on reads as a typo, and a warning that
            // reads as a typo is one somebody stops believing.
            $count === 1 ? 'stops' : 'stop',
            $this->subject,
        );
    }

    /**
     * The smallest complaint count that crosses the threshold on a given volume.
     *
     * ⚠️ **THIS CARRIED A `max(1, …)` AND IT WAS DEAD CODE — REMOVED AFTER A
     * MUTATION SURVIVED** (4394). The guard read *"never fewer than one, because
     * a formula answering 0 would render as 'you are already stopped' on a
     * spotless account"*, which is a real hazard and an unreachable one here:
     * both callers are behind {@see self::isArmed()}, which requires a floor
     * above zero, and the volumes they pass are the floor itself or a delivered
     * count at or above it — so `$delivered >= 1` and `$threshold >= 1`, and
     * `ceil()` of any positive number is at least one. **A guard nothing can
     * drive is a guard the next reader reasons from wrongly**: they will assume
     * the zero case happens. The bound is stated as an argument instead.
     */
    private function smallestTrippingCount(int $delivered): int
    {
        $threshold = (int) $this->thresholdBp;

        return (int) ceil($delivered * (2 * $threshold - 1) / 20_000);
    }

    private function complaintsAtFloorPhrase(): string
    {
        $count = (int) $this->complaintsToTripAtFloor();

        return $count === 1 ? 'one complaint' : number_format($count).' complaints';
    }

    private function complaintsCountedPhrase(): string
    {
        $count = (int) $this->complaints;

        return $count === 1 ? 'one complaint' : number_format($count).' complaints';
    }

    private function headroomPhrase(): string
    {
        if ($this->trips()) {
            return 'the stopping point is already reached';
        }

        $headroom = (int) $this->headroom();

        return $headroom === 1
            ? 'one more complaint stops it'
            : number_format($headroom).' more complaints stop it';
    }
}
