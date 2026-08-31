<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * One number's raw counters over one window — doc `51` §4.2's signals, before
 * any weight is applied to them.
 *
 * A plain value object rather than an array, so {@see NumberHealthService}'s
 * rate math has one spelling of each denominator rule instead of one per call
 * site — doc 51 §4.2: delivered and filtered rate divide by {@see self::$accepted},
 * STOP and reply rate divide by {@see self::$delivered}.
 */
final readonly class NumberHealthSignals
{
    /**
     * @param  array<string, int>  $byClass  Sends this window, keyed by
     *                                       outreach_messages.purpose. Doc 51
     *                                       §10's context column; no reader
     *                                       in this slice.
     */
    public function __construct(
        public int $sends = 0,
        public int $accepted = 0,
        public int $delivered = 0,
        public int $failedFiltered = 0,
        public int $failedOther = 0,
        public int $stops = 0,
        public int $replies = 0,
        public array $byClass = [],
    ) {}

    /**
     * Sum two windows, or two tenants' contributions to the same window —
     * the operation the shared Lane A pool's rollup runs once per business.
     */
    public function add(self $other): self
    {
        $byClass = $this->byClass;

        foreach ($other->byClass as $purpose => $count) {
            $byClass[$purpose] = ($byClass[$purpose] ?? 0) + $count;
        }

        return new self(
            sends: $this->sends + $other->sends,
            accepted: $this->accepted + $other->accepted,
            delivered: $this->delivered + $other->delivered,
            failedFiltered: $this->failedFiltered + $other->failedFiltered,
            failedOther: $this->failedOther + $other->failedOther,
            stops: $this->stops + $other->stops,
            replies: $this->replies + $other->replies,
            byClass: $byClass,
        );
    }

    /**
     * Messages this window whose outcome the carrier has actually reported.
     *
     * ⚠️ **THE DENOMINATOR IS DECIDED MESSAGES, NOT ROWS, AND THE DIFFERENCE
     * IS WHAT STOPS SILENCE READING AS FAILURE.** A row sits `Sent` from the
     * moment `Texter::send()` returns until slice 3's receipt lands, so under
     * an hourly recompute the most recent sends are routinely undecided.
     * Dividing by every row would score those as *not delivered* — indistinguishable,
     * in the arithmetic, from a carrier that refused them — and the number
     * would be marked down for the crime of having sent something recently.
     * Doc 51 §4.2's "accepted" is the carrier's accept, not our row count;
     * `accepted` on this object is `count(*)` (1645), so it is the wrong
     * denominator for a rate about outcomes.
     */
    public function decided(): int
    {
        return $this->delivered + $this->failedFiltered + $this->failedOther;
    }

    /**
     * Whether this window says anything at all about the number's health.
     *
     * ⚠️ **A WINDOW WITH NO DECIDED MESSAGE IS NOT A BAD WINDOW, IT IS A
     * SILENT ONE** — see {@see NumberHealthService::blend()}, which drops such
     * a window from the blend rather than scoring it at zero.
     */
    public function hasEvidence(): bool
    {
        return $this->decided() > 0;
    }

    /**
     * Delivered rate — delivered / decided (doc 51 §4.2).
     */
    public function deliveredRate(): float
    {
        return $this->decided() > 0 ? $this->delivered / $this->decided() : 0.0;
    }

    /**
     * Filter/failure rate — the `filtered` bucket / decided (doc 51 §4.2).
     */
    public function filteredRate(): float
    {
        return $this->decided() > 0 ? $this->failedFiltered / $this->decided() : 0.0;
    }

    /**
     * STOP rate, as a **percentage** — STOPs / delivered × 100 (doc 51 §4.2).
     *
     * A percentage rather than a fraction so it is directly comparable with
     * the registry's `numbers.quarantine_stop_pct`, which doc 51 §1 states as
     * a percentage (3.0, meaning 3%) rather than a fraction.
     */
    public function stopRatePercent(): float
    {
        return $this->delivered > 0 ? ($this->stops / $this->delivered) * 100 : 0.0;
    }

    /**
     * Inbound reply rate — non-STOP replies / delivered (doc 51 §4.2).
     */
    public function replyRate(): float
    {
        return $this->delivered > 0 ? $this->replies / $this->delivered : 0.0;
    }
}
