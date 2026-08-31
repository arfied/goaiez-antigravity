<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a reactivation campaign is in its life (T137 `SL-2`).
 *
 * ⚠️ **A STRING COLUMN CAST TO THIS ENUM, NEVER A POSTGRES ENUM TYPE.**
 * `CLAUDE.md`'s rule and an `ArchitectureTest` lint that fails the build on
 * `->enum(` in a migration.
 *
 * ⛔ **`Paused` HERE IS THE CAMPAIGN'S OWN STATE AND IS NOT THE KILL SWITCH.**
 * The per-tenant sending pause is `businesses.paused_at` through `TenantPause`,
 * the suspension is `TenantSuspension`, and the global halt is the
 * `sms.enabled` registry row — all three are read *per recipient* by
 * `RunCampaignJob` and none of them changes this column. Conflating them would
 * mean a tenant pause silently rewriting campaign state that the owner then has
 * to un-rewrite by hand, and — worse — a resumed tenant leaving every campaign
 * stopped with nothing saying why.
 */
enum CampaignStatus: string
{
    /** Being written. No audience resolved, nothing sent, nothing owed. */
    case Draft = 'draft';

    /**
     * Confirmed and waiting for its moment.
     *
     * ⚠️ **A CAMPAIGN REACHES THIS ONLY THROUGH A CONFIRMATION.** Decision 2106:
     * T137's *"GATE NOTHING — every SL feature ships LIVE"* does not touch
     * CONFIRM's three reserved cases, and *"the first send of a new campaign
     * type"* is one of them. Flip-gates and CONFIRM are different mechanisms.
     */
    case Scheduled = 'scheduled';

    /** Recipients are being sent to, a batch at a time. */
    case Running = 'running';

    /**
     * Stopped by a person, mid-flight, and resumable exactly where it stopped.
     *
     * The recipients already sent to keep their rows, so resuming sends to the
     * remainder rather than starting again — which is the whole reason the
     * audience is materialised into rows rather than recomputed from a query.
     */
    case Paused = 'paused';

    /** Every recipient has an outcome. */
    case Completed = 'completed';

    /**
     * Stopped for good.
     *
     * ⚠️ **NOT A DELETE.** The rows say who was sent to and who was not, and
     * that is the record a complaint is answered from.
     */
    case Cancelled = 'cancelled';

    /**
     * Whether the runner may send for a campaign in this state.
     *
     * A match with no default, so a seventh state is a compile-time
     * conversation rather than one that quietly inherits permission.
     */
    public function isSendable(): bool
    {
        return match ($this) {
            self::Scheduled, self::Running => true,
            self::Draft, self::Paused, self::Completed, self::Cancelled => false,
        };
    }
}
