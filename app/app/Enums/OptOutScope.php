<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How far an opt-out reaches.
 *
 * ⚠️ THE TWO CASES EXIST BECAUSE THE LANES ARE LITERALLY DIFFERENT PHONE
 * NUMBERS, not because someone wanted configurability. A carrier STOP is keyed
 * on the **sending number and the recipient** — never on whichever tenant
 * prompted the message — so the reach of an opt-out is decided by whose number
 * carried it:
 *
 *   Lane A   one shared toll-free number under the GO AI EZ brand (`25` §1.3).
 *            A STOP ends that number's right to text that person, and every
 *            tenant on Lane A sends from it. Platform scope.
 *
 *   Lane B   a dedicated local number per tenant. A STOP to the dentist says
 *            nothing about the mechanic, because they are two sender
 *            relationships on two numbers. Tenant scope.
 *
 * Decision 294 records that the shipped behaviour treated all suppression as
 * tenant-scoped, which is correct for exactly one of these.
 */
enum OptOutScope: string
{
    /**
     * Reaches every tenant. Written when a STOP arrives on the shared number.
     */
    case Platform = 'platform';

    /**
     * Reaches one tenant. Written when a STOP arrives on that tenant's own
     * number, and the tenant-owned `suppression_list` remains the richer record
     * of it — this row exists so that one query answers both scopes.
     */
    case Tenant = 'tenant';

    /**
     * Whether a row in this scope names a business.
     *
     * A match rather than a comparison so that a third scope cannot inherit an
     * answer nobody chose — the same reason `ConsentDisclosure::versionFor()`
     * has no default.
     */
    public function requiresBusiness(): bool
    {
        return match ($this) {
            self::Platform => false,
            self::Tenant => true,
        };
    }
}
