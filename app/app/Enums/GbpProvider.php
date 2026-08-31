<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Gbp\GbpConnections;

/**
 * Whose Google Business Profile access a location's reviews are read through.
 *
 * ⚠️ **BOTH CASES ARE PERMANENT, AND AN EARLIER READING OF THIS HAD ONE OF THEM
 * EXPIRING.** Decision 530 asked whether Zernio was a bridge until our own GBP
 * approval landed; the owner answered *neither* way on 2026-08-04 (546–548):
 * **both stay live and a location sits on one**. So this is not a feature flag
 * with a sunset and it is not a container binding — it is a per-location fact,
 * stored, resolved at call time by {@see GbpConnections}.
 *
 * ⚠️ **AND MOVING A LOCATION FROM `Zernio` TO `Direct` IS NOT OURS TO PERFORM.**
 * The tenant's Google grant lives inside Zernio's OAuth application, so changing
 * provider means that tenant re-authorising Google against ours. That is a
 * consent act only they can carry out — a button they press, one at a time,
 * never a bulk flip on our side (548).
 */
enum GbpProvider: string
{
    /**
     * Read through Zernio's approved Cloud project.
     *
     * The only case with an implementation today. Our own project sits at 0 QPM
     * until Google approves it, needs the profile verified 60+ days, and has no
     * sandbox — which is the whole reason this enum exists.
     */
    case Zernio = 'zernio';

    /**
     * Read through our own Google Business Profile API access.
     *
     * ⚠️ **No client implementation serves this case yet** (named in prose
     * rather than with a `{@see}` — the tag makes Pint add a real import, and
     * the lint holding client access to one service reads imports), and
     * `GbpConnections` refuses it rather than falling back to Zernio. A silent
     * fallback would read a tenant's reviews through a subprocessor they were
     * told they had moved off — which is a disclosure failure wearing the
     * costume of a sensible default.
     */
    case Direct = 'direct';

    /**
     * What an owner is told they are connected through.
     *
     * Named rather than hidden: `docs/SUBPROCESSOR-INVENTORY.md` §2 records that
     * a tenant grants Zernio's OAuth app **read and write** on their Business
     * Profile in one consent flow, so which of the two is serving a location is
     * a fact about who holds standing access to their listing — not an internal
     * implementation detail (535).
     */
    public function label(): string
    {
        return match ($this) {
            self::Zernio => 'Google, through our review partner',
            self::Direct => 'Google, directly',
        };
    }
}
