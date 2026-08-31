<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\SignalState;
use App\Services\Messaging\Outbound\SendSettlement;

/**
 * One rate on the sending dashboard, and what may honestly be said about it —
 * T137 §3.2's figures given §3 rail 2's alert thresholds.
 *
 * ## Why this exists at all, rather than the screen printing a percentage
 *
 * ⛔ **`SendingRates` RETURNS `0` FOR A RATE THAT DOES NOT EXIST, AND THAT IS
 * CORRECT OF IT AND FATAL ON A SCREEN.** Its `rate()` answers `0` whenever the
 * denominator is zero, deliberately — *"zero delivered is zero, not a division
 * by zero and not 100%"* — because a threshold comparison needs a number.
 * Rendering that same `0` as **"0.0%"** beside a green tick tells an operator
 * that this tenant's complaint rate has been measured and is excellent, when in
 * fact nothing has been measured at all.
 *
 * ⚠️ **THAT WAS NOT A HYPOTHETICAL — IT WAS THE STATE OF THE PLATFORM WHEN THIS
 * CLASS WAS WRITTEN, AND IT NO LONGER IS.** 2496–2499 found every counter in
 * `SendingHealth` writerless: `recordSent()`, `recordDelivered()`,
 * `recordFailed()`, `recordOptOut()` and `recordComplaint()` were called by
 * nothing outside their own class and the suite, so `sending_health_windows` was
 * empty for **every** tenant, including tenants that had been sent to. A
 * dashboard built on the obvious implementation would have shown a wall of
 * confident green zeroes over a containment that could not fire. 2498 records
 * that the platform-halt slice *"read the same empty counters and its tests
 * seeded them by hand, which is precisely how a writerless control survives
 * review."*
 *
 * ✅ **ALL FIVE COUNTERS HAVE WRITERS NOW, AND THIS PARAGRAPH WENT ON ASSERTING
 * THE OPPOSITE FOR A DAY AFTER FOUR OF THEM LANDED** (3038). `delivered` and
 * `failed` from the DLR webhook, `opted_out` and `complaints` from the STOP
 * handler, both 2026-08-12 (2520–2539); `sent` from
 * {@see SendSettlement}, 2026-08-13
 * (3030–3040). ⚠️ **The history above is kept rather than deleted**, because it
 * is why this class exists and why it must not be simplified away — but it is
 * history, and it was written in the present tense. Reading it as current is
 * `CLAUDE.md`'s 2505 failure, of which this docblock was an instance.
 *
 * So the denominator travels beside the rate, and **an empty denominator is
 * rendered as an absence rather than as a value**. That is load-bearing in the
 * ordinary way now rather than the emergency one: a tenant with traffic gets a
 * figure, a tenant without gets the honest dash.
 *
 * ## Every denominator on this screen is now a population that has been JUDGED
 *
 * ⛔ **THE DELIVERY RATE DIVIDED BY `sent`, AND A THRESHOLD OVER `sent` CANNOT
 * PROVE ANYTHING AT ALL — 2026-08-22 (7560–7563).**
 * `messaging.delivery_rate_alert_bp` became a **set** figure in the production
 * registry on 2026-08-22 — the first time any rate on this screen was watched
 * by anything. ⚠️ **What it holds today is not sayable from this repository**
 * (`CLAUDE.md`, 5913/6121): a seed is not a deployment, and the only honest
 * claim here is that the key is settable and is now read as a live alert. The
 * card built by the old `floor()` divided `delivered` by `sent` and carried
 * **no significance floor — the parameter did not exist on that factory** — so
 * the state machine below called it significant the instant it was measured,
 * and:
 *
 *   - **A brand new tenant's very first message read `0.0%` and went RED**,
 *     between the carrier accepting it and the receipt landing. Every tenant,
 *     every time.
 *   - ⛔ **And so did every ordinary campaign.** 500 handed over, 300 landed,
 *     5 failed and 195 not yet adjudicated is **60.0%** over `sent` — comfortably
 *     past a 90% floor, with nothing whatever wrong.
 *
 * ⚠️ **A VOLUME FLOOR ON `sent` DOES NOT FIX IT AND WAS REFUSED** (7561). The
 * complaint rate's floor works because `delivered` is *outcomes that came back*;
 * `sent` is *messages handed over, of which possibly none have been adjudicated*,
 * so a floor on it is **monotone with the thing that drags the rate down** — the
 * faster a burst clears the floor, the more un-adjudicated traffic sits in the
 * denominator. It moves the false alert from *message one* to *campaign one*.
 *
 * ✅ **THE FIX IS 1645's OWN RULE, WHICH THIS ONE RATE NEVER OBEYED: DIVIDE BY
 * WHAT HAS ACTUALLY BEEN DECIDED.** {@see SendingRates}'s own docblock states it
 * for the complaint rate — *"a complaint rate over `sent` under-reports and never
 * trips"* — and the delivery rate is the mirror image: over `sent` it
 * **over-reports failure and always alerts**, because an attempt with no verdict
 * has not failed to land, it has not been judged. {@see self::settled()} divides
 * by {@see SendingRates::outcomesReported()} instead, and the traffic that has
 * gone out travels beside it so nothing is hidden.
 *
 * ⚠️ **AND ONE DENOMINATOR IS NARROWER THAN ITS LABEL SUGGESTS** (3034). The
 * delivery rate counts only messages that were given a carrier handle, because
 * that is the only population a delivery receipt can ever land on — compliance
 * auto-replies and all email are outside it by construction. It is a carrier-SMS
 * delivery rate and is not the platform's overall one.
 *
 * ## Five states, not two — and the fifth is the one somebody paid for
 *
 * A rate is a signal only when several separate things are true, and each
 * failure has a different sentence because each needs a different action:
 *
 *   1. **Not measured** — the denominator is zero. Nothing to say, and saying
 *      "0%" would be saying something false. {@see SignalState::Unknown}.
 *   2. **Measured, unwatched** — no alert threshold is configured for this rate,
 *      so the figure is real and nothing is checking it. Also `Unknown`,
 *      because the *state* is what is unknown even though the number is not.
 *   3. **Measured, not yet significant** — there is traffic, but less than the
 *      significance floor requires, so the arithmetic is real and cannot be
 *      judged. `SendingRates::hasEnoughVolume()`'s rule, surfaced rather than
 *      left implicit: one STOP out of a new tenant's first two deliveries is a
 *      5,000bp rate, and showing that as an alert teaches an operator to raise
 *      the threshold until nothing ever fires (511's failure, in a kill switch).
 *   4. **Measured, watched and significant** — `Ok` or `Alert`, and only here is
 *      a colour carrying a threshold comparison.
 *   5. ⛔ **Nothing reported back at all, over real traffic** — an `Alert` that
 *      is **not** a threshold comparison and must never be confused with one.
 *      {@see self::reportingHasGoneSilent()}. This is the receipt blind spot of
 *      7480/7543: 4,000 messages handed to a carrier, not one outcome placeable,
 *      the complaint trip and the platform halt both silently disarmed, and
 *      every screen green. **Killing state 1's false positive must not delete
 *      this one** — it is what `delivery_rate_alert_bp` was set for (7543).
 *
 * ⚠️ **NO RATE FORMULA IS DUPLICATED FROM A CONTAINMENT.** {@see SendingRates}
 * divides for every rate a trip reads, and this class never touches those. What
 * it derives is one figure `SendingRates` does not expose — delivery over the
 * adjudicated population — and **nothing in `app/` compares that figure to
 * anything but the display threshold**, so there is no second reader for a
 * screen to disagree with. ⛔ **It belongs in `SendingRates` beside its
 * siblings and it is not written there because that file is nobody's this
 * wave** (7566); the move is one method and no behaviour.
 *
 * ⚠️ **COLOUR IS NEVER THE SIGNAL** (`22`, `29` §5.5). Every state here carries
 * a word and a sentence; `SignalState` supplies the icon. The colour is what
 * makes them fast to find.
 */
final readonly class RateReading
{
    /**
     * @param  ?int  $basisPoints  the rate {@see SendingRates} computed, or null
     *                             when there was no denominator to divide by —
     *                             never `0` standing in for "we did not measure"
     * @param  ?int  $thresholdBp  null or non-positive when nobody has set an
     *                             alert threshold for this rate
     * @param  int  $denominator  the population the rate is **over**, and on
     *                            this screen that is always a population
     *                            something has been reported about — delivered
     *                            messages for a ceiling rate, adjudicated ones
     *                            for {@see self::settled()}
     * @param  int  $significanceFloor  how much of that population the threshold
     *                                  needs before it may mean anything; `0`
     *                                  for a rate with no floor
     * @param  ?int  $handedOver  how much traffic produced that population —
     *                            null for a rate where the question does not
     *                            arise, and **the only thing that tells an empty
     *                            denominator meaning "nothing was sent" from one
     *                            meaning "nothing came back"** (7480–7486)
     * @param  ?string  $handedOverNoun  what that traffic is called on screen,
     *                                   null exactly where `$handedOver` is
     */
    private function __construct(
        public string $name,
        public ?int $basisPoints,
        public ?int $thresholdBp,
        public int $denominator,
        public string $denominatorNoun,
        public int $windowHours,
        public int $significanceFloor,
        public bool $alertsWhenAtOrAbove,
        public ?int $handedOver = null,
        public ?string $handedOverNoun = null,
    ) {}

    /**
     * A rate a threshold sits **above** — opt-outs and complaints, where higher
     * is worse.
     *
     * ⚠️ **THE DENOMINATOR IS A PARAMETER AND NOT AN AFTERTHOUGHT.** It is the
     * whole reason this class can tell "nobody complained about four hundred
     * delivered messages" from "nothing has been delivered", which
     * `$basisPoints` alone cannot: both are `0`.
     *
     * ⚠️ **`$significanceFloor` DEFAULTS TO `0` AND OMITTING IT IS A CHOICE
     * RATHER THAN A DEFAULT — 7564.** The opt-out card omitted it for as long as
     * it has existed, which was harmless only because
     * `messaging.opt_out_rate_alert_bp` was never set: the moment anybody sets
     * it, one STOP out of a new tenant's first delivery is a 10,000bp rate and
     * a red pill on their first day. The caller now passes the same floor the
     * Complaints card beside it uses, over the identical denominator.
     *
     * ⚠️ **NO SILENCE ARM HERE, AND THAT IS DELIBERATE.** A ceiling rate over
     * `delivered` whose denominator is empty is exactly the blind spot too — and
     * {@see TripMath} is the panel that says so for those, on this same screen,
     * with the volume beside it. Duplicating it into these two cards would put
     * the same sentence on the page three times.
     */
    public static function ceiling(
        string $name,
        int $basisPoints,
        int $denominator,
        string $denominatorNoun,
        int $windowHours,
        ?int $thresholdBp,
        int $significanceFloor = 0,
    ): self {
        return new self(
            name: $name,
            basisPoints: $denominator > 0 ? $basisPoints : null,
            thresholdBp: $thresholdBp !== null && $thresholdBp > 0 ? $thresholdBp : null,
            denominator: $denominator,
            denominatorNoun: $denominatorNoun,
            windowHours: $windowHours,
            significanceFloor: max(0, $significanceFloor),
            alertsWhenAtOrAbove: true,
        );
    }

    /**
     * The delivery rate — a threshold sitting **below** a figure taken over the
     * messages that have actually been adjudicated.
     *
     * ⛔ **THIS REPLACES `floor()`, WHICH DIVIDED BY `sent` AND COULD NOT ACCEPT
     * A SIGNIFICANCE FLOOR AT ALL** (7560–7563). The class docblock carries the
     * argument. `floor()` is not kept beside it: its two callers were both
     * delivery cards, both wrong in the same way, and **there is no other
     * floor-direction rate in this application** — shipping the general
     * parameter for a caller that does not exist is 256's vacuous lint with a
     * constructor's name on it.
     *
     * The direction is still a constructor fact rather than a guess made from
     * the name, because a delivery rate of 20% and a complaint rate of 20% are
     * the same number meaning opposite things, and a screen that got that
     * backwards would show a dying number pool as healthy.
     *
     * ⚠️ **THE ADJUDICATED RATE IS AN OPTIMISTIC BOUND AND THIS IS THE RIGHT
     * BOUND TO WATCH** (7565). Over `sent` the figure is pessimistic — every
     * outstanding message counts as a failure — and every alert it raises is
     * about lag. Over `reportedOn` it is optimistic: messages a carrier will
     * eventually call `EXPIRED` are outstanding until it says so, and Infobip's
     * validity period runs to **days** where this window is 24 hours. So **every
     * alert this raises is real**, which is the property an instrument needs to
     * go on being believed (511), and what it gives up is caught by two things
     * that need no invented number: the silence arm below, and
     * {@see self::outstanding()}, which is printed in the sentence whenever
     * anything is still unadjudicated.
     *
     * ⛔ **PARTIAL SILENCE IS THE RESIDUAL AND IT IS NOT CLOSED HERE** (7567): a
     * carrier reporting successes and never failures reads 100% for ever. The
     * count of outstanding messages is put in front of the operator; alerting on
     * it would need a coverage threshold, and that is a figure nobody has set
     * (2409).
     *
     * @param  int  $landed  `delivered`
     * @param  int  $reportedOn  {@see SendingRates::outcomesReported()} —
     *                           `delivered + failed`, the population a verdict
     *                           has arrived for
     * @param  int  $handedOver  `sent`
     * @param  int  $minimumSample  one figure doing two jobs, and they are the
     *                              same idea: how much has to be reported on
     *                              before the rate may be judged, and how much
     *                              may be handed over with nothing reported
     *                              before that silence is itself the alert
     */
    public static function settled(
        string $name,
        int $landed,
        int $reportedOn,
        int $handedOver,
        string $denominatorNoun,
        string $handedOverNoun,
        int $windowHours,
        ?int $thresholdBp,
        int $minimumSample = 0,
    ): self {
        $landed = max(0, $landed);
        $reportedOn = max(0, $reportedOn);
        $handedOver = max(0, $handedOver);

        return new self(
            name: $name,
            basisPoints: $reportedOn > 0 ? (int) round($landed * 10_000 / $reportedOn) : null,
            thresholdBp: $thresholdBp !== null && $thresholdBp > 0 ? $thresholdBp : null,
            denominator: $reportedOn,
            denominatorNoun: $denominatorNoun,
            windowHours: $windowHours,
            significanceFloor: max(0, $minimumSample),
            alertsWhenAtOrAbove: false,
            handedOver: $handedOver,
            handedOverNoun: $handedOverNoun,
        );
    }

    /**
     * Is there anything here at all?
     *
     * The denominator rather than the rate, for this class's whole reason.
     */
    public function isMeasured(): bool
    {
        return $this->denominator > 0;
    }

    /**
     * Is anything checking this figure?
     */
    public function hasThreshold(): bool
    {
        return $this->thresholdBp !== null;
    }

    /**
     * Is there enough traffic for the threshold to mean anything?
     *
     * True when no floor applies — a rate with no significance floor is
     * significant as soon as it is measured.
     */
    public function isSignificant(): bool
    {
        return $this->significanceFloor === 0 || $this->denominator >= $this->significanceFloor;
    }

    /**
     * Has real traffic gone out with **nothing at all** reported back about it?
     *
     * ⛔ **THE FIFTH STATE, AND THE ONE THE THRESHOLD WAS SET FOR** (7543,
     * 7562). An empty denominator means *"there is nothing to judge"* to every
     * other reader in this application, and it means *"the judge is
     * blindfolded"* just as often — a carrier reporting receipts under an id we
     * never stored produces the identical zero, with the complaint trip and the
     * platform halt both silently disarmed on a live campaign.
     *
     * ⚠️ **{@see SendingRates::trafficWithoutOutcomes()} IS THE PREDICATE AND
     * THIS IS NOT A SECOND ONE** — the same rule over this object's own fields,
     * exactly as {@see TripMath::reportingHasGoneSilent()} restates it, and for
     * the same reason: a screen and a containment must not disagree about a
     * tenant. Same comparison (`>=`), same emptiness test, same figure.
     *
     * ⚠️ **THE FLOOR IS THE CALLER'S AND IS DELIBERATELY THE TRIP'S OWN FIGURE**
     * (2409). A number invented here would be a policy nobody set. Under it,
     * silence is an ordinary campaign whose receipts have not landed yet, and
     * saying anything about that would be 511's failure in the one place a
     * containment is read from.
     *
     * False for every rate that carries no `$handedOver`, and false with no
     * floor configured — with no floor there is no trip to be blind about.
     */
    public function reportingHasGoneSilent(): bool
    {
        if ($this->handedOver === null || $this->significanceFloor <= 0) {
            return false;
        }

        return $this->handedOver >= $this->significanceFloor && $this->denominator === 0;
    }

    /**
     * How much has gone out that nothing has been said about yet.
     *
     * ⚠️ **PRINTED WHENEVER IT IS NON-ZERO, BECAUSE IT IS WHAT THE RATE LEAVES
     * OUT** (7565). A green 100% over three adjudicated messages and nine
     * hundred outstanding is true and is not the whole picture; this is the
     * cheapest possible way to put the rest of it on the screen, and it needs no
     * threshold and no figure from anybody.
     *
     * Null for a rate that carries no handed-over count.
     */
    public function outstanding(): ?int
    {
        if ($this->handedOver === null) {
            return null;
        }

        return max(0, $this->handedOver - $this->denominator);
    }

    /**
     * Has the threshold been crossed?
     *
     * ⚠️ **FALSE WHENEVER THE FIGURE IS NOT A SIGNAL**, rather than comparing
     * anyway. An unmeasured, unwatched or insignificant rate cannot breach
     * something: `0 <= 9000` is true of a delivery rate nobody measured, and a
     * screen that let that comparison run raises an alert about a tenant whose
     * first message is still in flight — which is what it did, for every tenant,
     * from the moment the threshold was set (7560).
     *
     * ⚠️ **SILENCE IS NOT A BREACH AND MUST NOT BE REPORTED AS ONE.** It cannot
     * reach this method — silence implies an empty denominator — and the two are
     * kept apart in {@see self::state()} deliberately: one says *this tenant's
     * delivery is bad*, the other says *we cannot see this tenant at all*, and
     * they call for opposite actions.
     */
    public function breached(): bool
    {
        if (! $this->isMeasured() || ! $this->isSignificant()) {
            return false;
        }

        $threshold = $this->thresholdBp;

        if ($threshold === null) {
            return false;
        }

        $observed = (int) $this->basisPoints;

        return $this->alertsWhenAtOrAbove
            ? $observed >= $threshold
            : $observed <= $threshold;
    }

    /**
     * The signal, which is `Unknown` for three of the five states.
     *
     * ⚠️ **`Unknown` IS NOT A FOURTH SEVERITY** — {@see SignalState} says so in
     * its own docblock: *"it is the absence of a measurement … a component given
     * null must render a dash and a plain sentence rather than an empty dial
     * that reads as nought out of a hundred."*
     *
     * ⚠️ **THE SILENCE ALERT IS GATED ON A THRESHOLD BEING SET, AND THAT IS
     * `TripMath`'s ORDERING RATHER THAN A NEW ONE** (7562) — its `stateLabel()`
     * asks `isArmed()` before it says anything about silence. A card whose pill
     * reads *"No alert set"* must not also be the card that goes red; **the
     * label below says the true thing either way**, which is `22`'s rule that
     * colour is never what carries the signal. ⚠️ **So an unwatched platform
     * whose reporting has stopped is a grey pill with a black sentence, and
     * nothing here pages anybody about it** — the bell is 7493(a)'s and is not
     * on a screen at all.
     */
    public function state(): SignalState
    {
        if (! $this->hasThreshold()) {
            return SignalState::Unknown;
        }

        if ($this->reportingHasGoneSilent()) {
            return SignalState::Alert;
        }

        if (! $this->isMeasured() || ! $this->isSignificant()) {
            return SignalState::Unknown;
        }

        return $this->breached() ? SignalState::Alert : SignalState::Ok;
    }

    /**
     * The word beside the colour, which says which kind of "we cannot say".
     *
     * `SignalState::label()`'s own words are deliberately overridden for the
     * unknowns: "Not checked" is true of all of them and useful for none,
     * and the difference between them is what an operator has to act on —
     * wire the counters, set a threshold, wait for traffic, or go and find out
     * why a carrier has stopped answering.
     *
     * ⛔ **SILENCE IS TESTED FIRST BECAUSE TWO ARMS ARE TRUE AND ONLY ONE IS THE
     * ANSWER** (7562), which is exactly the ordering `TripMath::stateLabel()`
     * needed for the same reason: a tenant whose receipts have gone silent has
     * an empty denominator **by construction**, so "Not measured yet" is what
     * this card said about 4,000 messages on the wire.
     */
    public function stateLabel(): string
    {
        return match (true) {
            $this->reportingHasGoneSilent() => 'Nothing is being reported back',
            ! $this->isMeasured() => 'Not measured yet',
            ! $this->hasThreshold() => 'No alert set',
            ! $this->isSignificant() => 'Not enough sending yet',
            $this->breached() => 'Past the alert threshold',
            default => 'Within the alert threshold',
        };
    }

    /**
     * The figure, or a dash.
     *
     * ⛔ **A DASH RATHER THAN "0.0%", AND THIS IS THE LINE THE WHOLE CLASS
     * EXISTS FOR.** An em dash cannot be misread as a measurement; a zero can,
     * and reads as the best possible one.
     *
     * ⚠️ **A SILENT TENANT GETS THE DASH TOO, AND THAT IS THE HONEST FIGURE.**
     * The old card printed **"0.0% of 4,000 handed to a carrier"** in that
     * state (7483) — a confident measurement of a thing nobody had measured.
     * What carries the alarm is the pill and the sentence, not a number.
     */
    public function figure(): string
    {
        if ($this->basisPoints === null) {
            return '—';
        }

        return number_format($this->basisPoints / 100, 1).'%';
    }

    /**
     * The threshold, or a plain statement that there is not one.
     */
    public function thresholdFigure(): string
    {
        if ($this->thresholdBp === null) {
            return 'none set';
        }

        return number_format($this->thresholdBp / 100, 1).'%';
    }

    /**
     * One plain sentence, `29` §5.3's rule: what this means, never a restatement
     * of the metric.
     *
     * Outcome language (`22`) and **no personal data** — a count of messages and
     * a percentage, never a number, a recipient or a business name.
     */
    public function sentence(): string
    {
        if ($this->reportingHasGoneSilent()) {
            return $this->unwatchedSuffix(sprintf(
                '%s %s in the last %d hours and not one outcome reported back, so there is no rate '
                .'to show. This is not a quiet window — it is a measurement that cannot happen, and '
                .'nothing here can tell a healthy send from a failing one.',
                number_format((int) $this->handedOver),
                (string) $this->handedOverNoun,
                $this->windowHours,
            ));
        }

        if (! $this->isMeasured()) {
            return $this->unwatchedSuffix(sprintf(
                'Nothing has been %s for this business in the last %d hours, so there is no rate to '
                .'show. An empty counter is not a zero rate.%s',
                $this->denominatorNoun,
                $this->windowHours,
                $this->outstandingClause(),
            ));
        }

        $basis = sprintf(
            '%s of %s %s in the last %d hours',
            $this->figure(),
            number_format($this->denominator),
            $this->denominatorNoun,
            $this->windowHours,
        );

        if (! $this->hasThreshold()) {
            return $basis.'. No alert threshold is set for this rate, so nothing is watching it.'
                .$this->outstandingClause();
        }

        if (! $this->isSignificant()) {
            return sprintf(
                '%s — too little to judge. The threshold waits for %s %s before it means anything.%s',
                $basis,
                number_format($this->significanceFloor),
                $this->denominatorNoun,
                $this->outstandingClause(),
            );
        }

        return sprintf(
            '%s, %s the %s threshold.%s',
            $basis,
            $this->breached() ? 'past' : 'within',
            $this->thresholdFigure(),
            $this->outstandingClause(),
        );
    }

    /**
     * What the rate leaves out, said in the same breath as the rate.
     *
     * Empty when there is nothing outstanding or when this rate carries no
     * handed-over count, so a caller never prints reassurance.
     */
    private function outstandingClause(): string
    {
        $outstanding = $this->outstanding();

        if ($outstanding === null || $outstanding === 0) {
            return '';
        }

        return sprintf(
            ' %s more %s and no outcome reported back yet, so this figure is only about the ones '
            .'that have been.',
            number_format($outstanding),
            (string) $this->handedOverNoun,
        );
    }

    /**
     * The clause that says nobody is checking — appended to the two sentences
     * that are about an absence rather than a figure, because those two are the
     * ones an operator most needs to know are unwatched.
     */
    private function unwatchedSuffix(string $sentence): string
    {
        if ($this->hasThreshold()) {
            return $sentence;
        }

        return $sentence.' No alert threshold is set for this rate, so nothing is watching it.';
    }
}
