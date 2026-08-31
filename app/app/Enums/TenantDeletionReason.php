<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Tenant\TenantDeletion;

/**
 * Why an account is being deleted.
 *
 * `28` §9.5 names exactly two — *"Delete (right-to-erasure or hard offboard)"* —
 * and they are kept apart rather than collapsed into one "deleted" state,
 * because the two answer to different clocks and different readers.
 *
 * ⚠️ **A right-to-erasure request has a statutory deadline and a hard offboard
 * does not.** GDPR Art. 12(3) is one month from the request; CCPA is 45 days.
 * A tenant who simply left can wait, and one exercising a right cannot — so a
 * queue that cannot tell them apart cannot be triaged, and the first sign of
 * the difference would be a missed deadline. `28` §9.5's *"request queue with
 * due dates"* is the reader this distinction exists for.
 *
 * ⚠️ **It is also the difference between "stop processing my data" and "close
 * my account", and only the first survives the account being closed.** The
 * erasure obligation attaches to the person, so it reaches records this
 * application deliberately keeps after a business is gone — see
 * {@see TenantDeletion} on the four tables that outlive
 * the tenant, and what v1 can and cannot do about them.
 */
enum TenantDeletionReason: string
{
    /**
     * The data subject asked to be erased.
     *
     * Statutory clock, and the reason the redaction gap in
     * {@see TenantDeletion} is recorded rather than
     * quietly accepted: this is the case it falls short of.
     */
    case RightToErasure = 'right_to_erasure';

    /**
     * The account is being closed, with no rights claim attached.
     *
     * A churned tenant, a duplicate account, a test tenant that reached
     * production. No statutory deadline.
     */
    case HardOffboard = 'hard_offboard';

    /**
     * Outcome language, `22`'s rule: name what happens, never how it is built.
     */
    public function label(): string
    {
        return match ($this) {
            self::RightToErasure => 'Erasure requested by the customer',
            self::HardOffboard => 'Account closed',
        };
    }

    /**
     * Does this reason carry a statutory deadline?
     *
     * The *number* of days is deliberately not here. It varies by regime (one
     * month under GDPR Art. 12(3), 45 days under CCPA) and by where the data
     * subject is — so a concrete due date computed here would be a guess wearing
     * a deadline's clothes. This answers only whether a clock exists; `28` §9.5's
     * due-date queue is where the number belongs, with the region that decides
     * it.
     *
     * ⚠️ **`customers.region_code` HAS A WRITER NOW (1594) AND STILL DOES NOT
     * SETTLE THIS.** That column is the *customer's* jurisdiction for `29` §2
     * rule 11's messaging windows; a tenant deletion's clock is set by where the
     * **tenant** is and by which regime they fall under, which nothing in this
     * schema records. Reading the messaging column as the privacy one is exactly
     * the plausible wrong proxy this codebase keeps refusing.
     */
    public function isStatutory(): bool
    {
        return $this === self::RightToErasure;
    }
}
