<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Consent\ConsentService;
use App\Services\Export\ExportBuilder;
use App\Services\Support\DataRequests;
use App\Services\Tenant\TenantDeletion;

/**
 * What a subject-access / privacy request asks for (`28` §9.5).
 *
 * Three kinds, and they are not interchangeable: an erasure destroys an
 * account through {@see TenantDeletion}, a consent audit
 * is a per-contact trail through {@see ConsentService}, and a tenant-wide
 * export is built through {@see ExportBuilder} once a second person approves
 * it — {@see DataRequests::approveTenantExport()}
 * (decision 1824). It used to be filed and immediately refused; §3.7's engine
 * exists now.
 */
enum DataRequestKind: string
{
    /** GDPR/CCPA right-to-erasure / hard offboard of the tenant account. */
    case Erasure = 'erasure';

    /** Per-contact consent audit (Lane requirement, `25` / COMP-01). */
    case ConsentAudit = 'consent_audit';

    /**
     * Full tenant data export (`28` §3.7).
     *
     * ⚠️ Filed so the queue records the ask; fulfilled as a refusal until the
     * export engine exists — a button that produces nothing is worse than an
     * honest refusal (1382).
     */
    case TenantExport = 'tenant_export';

    public function label(): string
    {
        return match ($this) {
            self::Erasure => 'Erase this account',
            self::ConsentAudit => 'Consent audit for one contact',
            self::TenantExport => 'Download all of this account\'s data',
        };
    }

    /*
     * ⚠️ **THERE IS DELIBERATELY NO `isStatutory()` HERE, AND THERE WAS ONE.**
     * It returned true for `Erasure`, which read correctly and was wrong: a hard
     * offboard — us terminating an account as a business decision, no data
     * subject and no Art. 12 request anywhere — is filed under this same kind,
     * because it destroys an account through the same {@see TenantDeletion} and
     * shares the one-open-deletion-per-business index. So the kind cannot answer
     * the question: two rows with `kind = erasure` can have opposite answers.
     *
     * The reason *can* answer it, and already does —
     * {@see \App\Enums\TenantDeletionReason::isStatutory()} on the linked
     * deletion request, which is where the distinction is actually recorded.
     *
     * Nothing read the removed method: `28` §9.5's due-date window is
     * {@see \App\Services\Support\DataRequests::STATUTORY_DUE_DAYS} for every
     * kind, deliberately, so nothing sits undated. Which is why this is a
     * comment rather than a replacement method — a control with no reader is
     * how a false claim survives long enough to be believed, and the honest
     * move is to delete the claim rather than to build a second one nobody
     * asked for. Whoever builds a real per-regime clock reads the reason.
     */
}
