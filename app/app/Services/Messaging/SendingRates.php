<?php

declare(strict_types=1);

namespace App\Services\Messaging;

/**
 * One tenant's sending outcomes over the rolling window, and the rates derived
 * from them — T137 §3.2's dashboard figures and §3's alert thresholds.
 *
 * ## Every rate is basis points
 *
 * 200 is 2%. Integers, for the reason this codebase stores money as cents: a
 * rate is compared against a configured threshold and stored on a pause row, and
 * a float that survives one JSON round trip as `0.019999999999` compares wrong
 * against itself.
 *
 * ## The denominators are not the same, and that is the whole design
 *
 * ⚠️ **A COMPLAINT RATE OVER `sent` UNDER-REPORTS AND NEVER TRIPS.** Messages
 * sent in the last few minutes have no outcome yet — a row is `Sent` from the
 * moment the carrier accepts it until a delivery receipt lands — so dividing
 * complaints by everything sent means a campaign in flight always looks clean,
 * which is exactly when the trip needs to fire. `number_health_daily`'s scorer
 * hit this and 1645 records it: **divide by what has actually been decided.**
 *
 * So `complaintRateBp()` and `optOutRateBp()` divide by {@see self::$delivered},
 * because you cannot complain about a message that never arrived, while
 * `deliveryRateBp()` divides by {@see self::$sent} because its question is
 * precisely how many of the attempts landed.
 *
 * ⚠️ **AND ZERO DELIVERED IS ZERO, NOT A DIVISION BY ZERO AND NOT 100%.** A
 * tenant who has sent nothing has no complaint rate; the minimum-volume floor in
 * {@see SendingGuard} is what stops one complaint out of three deliveries
 * reading as a 3,333bp catastrophe.
 *
 * ## The third question, which nothing asked for nine days
 *
 * ⛔ **`delivered` IS BOTH THE DENOMINATOR OF THE TRIP AND THE PROOF THAT
 * ANYTHING IS REPORTING BACK, AND THOSE ARE DIFFERENT FACTS** (7480). An empty
 * `delivered` means *"there is nothing to judge"* to every reader in this
 * application — and it means *"the judge is blindfolded"* just as often, because
 * a delivery receipt has exactly one join key and a carrier that reports under a
 * different one produces the identical zero. {@see self::trafficWithoutOutcomes()}
 * is the one predicate that tells those apart, and it needs nothing this class
 * did not already hold.
 */
final readonly class SendingRates
{
    public function __construct(
        public int $sent = 0,
        public int $delivered = 0,
        public int $failed = 0,
        public int $optedOut = 0,
        public int $complaints = 0,
    ) {}

    /**
     * How many of the messages handed to a carrier actually landed.
     */
    public function deliveryRateBp(): int
    {
        return $this->rate($this->delivered, $this->sent);
    }

    /**
     * How many of the people who received a message asked to stop.
     */
    public function optOutRateBp(): int
    {
        return $this->rate($this->optedOut, $this->delivered);
    }

    /**
     * The figure the automatic trip reads (2102).
     */
    public function complaintRateBp(): int
    {
        return $this->rate($this->complaints, $this->delivered);
    }

    /**
     * Whether there is enough traffic here for a rate to mean anything.
     *
     * ⚠️ **THE GUARD ASKS THIS BEFORE IT ASKS FOR A RATE, AND SKIPPING IT IS THE
     * DEFECT THIS METHOD EXISTS TO PREVENT.** One STOP out of the first two
     * deliveries of a brand new tenant is a 5,000bp complaint rate and would
     * pause them permanently on their second-ever message. Every tenant starts
     * at zero traffic, so without a floor the trip fires for all of them.
     */
    public function hasEnoughVolume(int $minimum): bool
    {
        return $this->delivered >= $minimum;
    }

    /**
     * How many of the messages handed to a carrier have been reported on at all.
     *
     * ⚠️ **BOTH OUTCOMES, BECAUSE EITHER ONE PROVES SOMETHING CAME BACK.** A
     * carrier reporting `UNDELIVERABLE` about every message is a disaster of a
     * completely different kind, and this figure is not about whether messages
     * landed — it is about whether **anything was ever said about them**.
     */
    public function outcomesReported(): int
    {
        return $this->delivered + $this->failed;
    }

    /**
     * Have messages gone out with **nothing at all** reported back about them?
     *
     * ⛔ **THIS EXISTS BECAUSE {@see self::hasEnoughVolume()} CANNOT TELL TWO
     * OPPOSITE WORLDS APART, AND THE CONTAINMENT IS BUILT ON IT** (7480–7483).
     * {@see SendingGuard}'s `shouldTrip()` and {@see PlatformRateSample::trips()}
     * both decline to act when `delivered` is under a floor, and `delivered` is
     * under a floor in each of these:
     *
     *   1. **Nothing has been sent.** Correct and benign — there is nothing to
     *      judge, and it is the state of every tenant on their first day.
     *   2. **Thousands have been sent and not one receipt could be placed.** The
     *      automatic complaint trip that 2101/2102/2113 make a precondition of
     *      sending at all is **disabled**, silently, with traffic on the wire.
     *
     * The two are indistinguishable from `delivered` alone and trivially
     * distinguishable with `sent` beside it — which this object has carried
     * since it was written. **Nothing had ever asked.**
     *
     * ⚠️ **THE FLOOR IS THE CALLER'S AND IS DELIBERATELY THE TRIP'S OWN FIGURE**
     * (2409). A number invented here would be a policy nobody set; reusing
     * `messaging.complaint_trip_min_delivered` makes the claim exact rather than
     * approximate — *as many messages have been handed to a carrier as the trip
     * needs to have been **delivered**, and not one of them has been reported
     * on.* Under that, silence is an ordinary campaign whose receipts have not
     * landed yet, and saying anything about it would be 511's failure in the one
     * place a containment is read from.
     *
     * ⚠️ **A NON-POSITIVE FLOOR ANSWERS FALSE**, matching both trips: with no
     * floor configured there is no trip to be blind about, and each guard has
     * already refused on that arm before this is asked.
     *
     * ⛔ **IT IS A QUESTION AND NEVER A BRAKE (R25).** Nothing may refuse a send
     * on this answer. A blind spot in the containment is not evidence that this
     * tenant did anything wrong, and stopping them for it would punish a
     * business for a vendor's reporting — while leaving the tenant who *is*
     * generating complaints running, because the same silence hides them both.
     */
    public function trafficWithoutOutcomes(int $minimum): bool
    {
        if ($minimum <= 0) {
            return false;
        }

        return $this->sent >= $minimum && $this->outcomesReported() === 0;
    }

    private function rate(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            return 0;
        }

        return (int) round($numerator * 10_000 / $denominator);
    }
}
