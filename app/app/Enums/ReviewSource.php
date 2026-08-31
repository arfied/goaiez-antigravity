<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a review came from (DATA-MODEL §5.1 `review_source`).
 *
 * First-party reviews and Google reviews are two pipelines that must never be
 * confused (`29` §2): Google reviews cannot be held, hidden, approved, or
 * moderated; first-party ones flow through routing and triage.
 *
 * Yelp appears here as an ingest source only. It is never an invitation
 * destination (decision 112) — that ban lives in review-destination config,
 * not in this enum, because reading a Yelp review is fine and soliciting one
 * is prohibited.
 */
enum ReviewSource: string
{
    case FirstParty = 'first_party';
    case Google = 'google';
    case Facebook = 'facebook';
    case Yelp = 'yelp';
    case Apple = 'apple';
}
