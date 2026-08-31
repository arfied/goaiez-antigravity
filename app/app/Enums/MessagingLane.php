<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which consent lane a contact may be messaged through (DATA-MODEL §5.6,
 * `24` §Lanes).
 *
 * DERIVED, never set by hand: our surface + disclosure + proof → Platform;
 * tenant-asserted consent → Tenant; no valid record → None, which is
 * unsendable. `customers.messaging_lane` is guarded on the model and refreshed
 * by jobs from consent_records — code that wants to change a lane changes the
 * consent record, never this value.
 */
enum MessagingLane: string
{
    /**
     * Platform-captured consent. Live day one; carries the "via GO AI EZ"
     * disclosure and sends from platform-owned numbers.
     */
    case Platform = 'platform';

    /**
     * Tenant-branded. Requires the tenant's own TCR brand registration.
     */
    case Tenant = 'tenant';

    /**
     * No valid consent record. A contact in this lane can never be texted at
     * all (`29` §2) — this is the default, and the safe state.
     */
    case None = 'none';
}
