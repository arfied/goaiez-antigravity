<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two facts `28` §9.1 requires recorded about an internal account.
 *
 * *"Every internal login and role change → `audit_log`."* One sentence, two
 * events, and **neither can go in `audit_log`** — it is `NOT NULL` on
 * `business_id` and RLS-`FORCE`d on `app.business_id`, and an internal staff
 * member belongs to no business (decision 741). `staff_events` is the
 * platform-scoped store that sentence actually needs.
 *
 * A `string` column cast to this enum, never a database enum (CLAUDE.md). A
 * CHECK constraint closes the same set at the database, on slice B's three-layer
 * reasoning (303–316), because the column decides what a row *means* and a row
 * with an unknown event is a row the screen renders as nothing.
 *
 * ⚠️ **DELIBERATELY CLOSED, AND IT IS NOT A GENERAL INTERNAL AUDIT LOG.** Two
 * cases, because §9.1 names two facts. The attraction of a third — "internal
 * staff did something, put it here" — is exactly how a store with one writer
 * becomes a second `audit_log` with none of its constraints and no tenant. What
 * belongs in a tenant's compliance record still goes there; `AuditService` is
 * unchanged and reaches it under the tenant's own boundary.
 */
enum StaffEvent: string
{
    /**
     * An internal account signed in, however they got in.
     *
     * Recorded on `Illuminate\Auth\Events\Login` rather than at a call site,
     * because there are four ways in and two of them are inside Fortify with no
     * controller of ours to edit — decision 661's finding, which is the same
     * finding in the other direction: what a control must cover, a record must
     * cover too.
     */
    case SignedIn = 'signed_in';

    /**
     * Somebody's role moved, with the value on both sides of it and a reason.
     *
     * One case rather than granted/changed/revoked: which of the three it was
     * is derivable from `role_before` and `role_after` and cannot disagree with
     * them, where three cases can — a row saying "revoked" with an `ops_admin`
     * on the far side is a row that has to be read twice to be believed.
     */
    case RoleChanged = 'role_changed';

    /**
     * How this event is named to an operator reading the trail.
     */
    public function label(): string
    {
        return match ($this) {
            self::SignedIn => 'Signed in',
            self::RoleChanged => 'Role changed',
        };
    }
}
