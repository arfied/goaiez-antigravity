<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a first-party review (DATA-MODEL §5.1 `review_status`).
 *
 * These states gate the first-party pipeline only. A Google review is never
 * held, hidden, approved, or moderated (`29` §2 rule 1) — rows ingested from
 * Google carry a status for bookkeeping, never for gating.
 */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Flagged = 'flagged';
    case Displayed = 'displayed';
    case InTriage = 'in_triage';
    case Resolved = 'resolved';
}
