<?php

declare(strict_types=1);

namespace App\Services\Messaging;

/**
 * What the platform's complaint rate was, and what it was measured from.
 *
 * The platform twin of {@see SendingRates}, and deliberately a different type
 * rather than a reused one: `SendingRates` answers *one tenant's* dashboard
 * questions (delivery, opt-out, complaint) and carries `opted_out` that a
 * platform trip has no use for, while this carries `tenantCount`, which is
 * meaningless for a single tenant. **A shared type would have fields that are
 * null in one of its two uses**, and somebody would eventually read one.
 *
 * ⛔ **"CARRIES `sent` AND `failed` THAT A PLATFORM TRIP HAS NO USE FOR" WAS
 * WRITTEN AS A REASON THIS TYPE IS NARROWER, AND IT WAS THE HOLE — CORRECTED
 * 2026-08-22 (7480, 7487).** It is true of the *rate* and false of the *trip*:
 * {@see self::trips()} refuses whenever `delivered` is under a floor, and an
 * empty `delivered` means *"nothing has been sent"* and *"nothing has been
 * reported back"* equally well. Without `sent` beside it, **2102's platform-wide
 * halt could be switched off by a carrier reporting under an id we never
 * stored, across every tenant at once, and no figure in this sample could have
 * said so** — while `PlatformComplaintRate`'s own sweep read a spotless
 * platform. The two counters are here now for that one question and for nothing
 * else; the rate still divides by `delivered` and always will.
 */
final readonly class PlatformRateSample
{
    public function __construct(
        public int $delivered,
        public int $complaints,
        public int $tenantCount,
        public int $windowHours,
        /**
         * ⛔ **REQUIRED RATHER THAN DEFAULTED TO ZERO, AND THE DEFAULT WAS
         * WRITTEN FIRST AND REMOVED** (7487). `sent = 0` makes
         * {@see self::reportingHasGoneSilent()} answer false at every call site
         * that forgets it — a detector that is present, plausible and off,
         * which is `CLAUDE.md`'s most dangerous shape and is the very failure
         * this field exists to catch. **Every construction has to state what
         * went out.**
         */
        public int $sent,
        public int $failed,
    ) {}

    /**
     * Complaints per ten thousand delivered messages.
     *
     * Divides by `delivered` rather than `sent`, which is 1645's lesson and
     * `SendingRates`' rule: a message with no outcome yet cannot have been
     * complained about, so dividing by everything sent makes a campaign in
     * flight look clean at exactly the moment the trip needs to fire.
     */
    public function basisPoints(): int
    {
        if ($this->delivered <= 0) {
            return 0;
        }

        return (int) round($this->complaints * 10_000 / $this->delivered);
    }

    /**
     * Whether this sample crosses a threshold that is switched on at all.
     *
     * ⚠️ **BOTH NUMBERS MUST BE SET, AND AN UNSET ONE MEANS "DO NOT TRIP"
     * RATHER THAN "TRIP ON EVERYTHING".** The per-tenant twin says the same
     * (`messaging.complaint_trip_bp`: *zero or negative disables the automatic
     * trip entirely*), and the reason is stronger here: a misconfigured platform
     * threshold does not pause one tenant, it stops every message this company
     * sends. ⛔ **A floor of zero with a live threshold would trip on the first
     * complaint of the first hour of the first day**, so the floor being unset
     * disables the trip too — the two are one setting in two boxes.
     */
    public function trips(int $thresholdBasisPoints, int $minimumDelivered): bool
    {
        if ($thresholdBasisPoints <= 0 || $minimumDelivered <= 0) {
            return false;
        }

        if ($this->delivered < $minimumDelivered) {
            return false;
        }

        return $this->basisPoints() >= $thresholdBasisPoints;
    }

    /**
     * Has anything at all been reported back about the platform's traffic?
     *
     * ⚠️ **{@see SendingRates::trafficWithoutOutcomes()}'S RULE, OVER THE SAME
     * ARITHMETIC, FOR THE SAME REASON ONE LAYER UP** (7480–7487). The per-tenant
     * twin carries the full argument; what is different here is only the blast
     * radius. A tenant whose receipts have gone silent has one containment off;
     * a **platform** whose receipts have gone silent has 2102's halt off for
     * every tenant sending over the one shared GOAIEZ 10DLC registration, which
     * is the exact scenario 2101 accepted the attestation override on.
     *
     * ⛔ **A QUESTION AND NEVER A BRAKE (R25).** {@see self::trips()} does not
     * consult it and must not: halting the platform because our own reporting
     * is broken would stop every tenant's carrier-mandated STOP confirmation
     * and HELP answer through {@see SendingGuard}, which 2099 makes
     * unconditional — the exact collision 3980–3983 split the two halt keys to
     * prevent, arriving through a different door.
     */
    public function reportingHasGoneSilent(int $minimumDelivered): bool
    {
        if ($minimumDelivered <= 0) {
            return false;
        }

        return $this->sent >= $minimumDelivered && $this->delivered + $this->failed === 0;
    }
}
