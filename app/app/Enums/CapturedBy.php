<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who captured a consent record — and therefore which lane, and therefore which
 * phone number, a text is sent from (`25` §1.3, DATA-MODEL §5.6).
 *
 * This was a raw string until row 3 slice A. It is the field the lane derives
 * from, so an unconstrained value here is not a data-quality problem, it is a
 * routing problem: 'platfrom' would derive no lane at all and the contact would
 * silently become untextable, with nothing anywhere reporting a fault.
 */
enum CapturedBy: string
{
    /**
     * Our surface, our disclosure, our stored proof. Lane A — the platform's
     * verified toll-free number, live day one, carrying "via GO AI EZ".
     */
    case Platform = 'platform';

    /**
     * Tenant-asserted: import, POS, call, paper, verbal. Lane B — the tenant's
     * own dedicated local number, requiring their own TCR brand.
     */
    case Tenant = 'tenant';

    /**
     * The derivation rule of `25` §1.3, in one place.
     *
     * Note there is no path to MessagingLane::None here: "no lane" is the
     * absence of a consent record, not a property of one.
     */
    public function lane(): MessagingLane
    {
        return match ($this) {
            self::Platform => MessagingLane::Platform,
            self::Tenant => MessagingLane::Tenant,
        };
    }
}
