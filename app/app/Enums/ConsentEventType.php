<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two kinds of thing that appear in a consent trail.
 *
 * They live in different tables — `consent_records` and `suppression_list` — for
 * a good reason (decision 289: consent is append-only and carries no revoked
 * state, so a withdrawal is a suppression entry rather than an edit to the
 * grant). But `17` COMP-01 and `29` §19.4 ask for one artifact: the complete
 * trail for a contact, in order. Two tables, one question — this is what
 * reconciles them.
 */
enum ConsentEventType: string
{
    case Consent = 'consent';
    case Withdrawal = 'withdrawal';
}
