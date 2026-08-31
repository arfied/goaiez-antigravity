<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Actuation\ActuationActor;
use App\Services\AuditService;

/**
 * Which kind of decision applied a site change, or undid one.
 *
 * ⛔ **THIS EXISTS SO THAT AN OWNER UNDO AND AN AUTO-REVERT ARE NEVER THE SAME
 * FACT** (`BUILD-PLAN` §2.11.5 correction 4, decision 5524). `DATA-MODEL.md`'s
 * spec records only `rolled_back_at`, which answers *when* and cannot answer
 * *whose call it was* — and the two lead opposite ways: slice H quarantines the
 * automation when the platform reverts its own work, while slice J's screen is
 * a customer telling us we were wrong and must never be reported as a system
 * self-correction.
 *
 * ⚠️ **THE PERSON IS NOT HERE, AND IS NOT LOST.** This names the *kind* of
 * actor because that is what the feed, the quarantine and the undo screen branch
 * on; which human it was rides in the append-only audit row, where
 * {@see AuditService}'s `user:14` vocabulary already lives and
 * where a sensitive action belongs. {@see ActuationActor}
 * is what makes the pairing impossible to get wrong.
 */
enum SiteChangeActor: string
{
    /** The platform decided, under `automation_mode = 'auto'`. */
    case Autopilot = 'autopilot';

    /** The business owner asked, on their own screen. */
    case Owner = 'owner';

    /** Platform staff acted, on the tenant's behalf and on the record. */
    case Staff = 'staff';

    /**
     * Whether a person made this call.
     */
    public function isHuman(): bool
    {
        return $this !== self::Autopilot;
    }
}
