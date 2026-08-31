<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a `phone_numbers` row is for (doc 51 §2.1, §10).
 *
 * A location's `primary` number is its texting identity — inbound, missed-call
 * text-back, conversations, the number swap of Master 9.6. An `extension` exists
 * only to carry first-touch outbound volume when the primary's caps are not
 * enough (doc 51 §2.1, §2.3 — opt-in, purchased by an explicit owner click; not
 * built in this slice). `sharedPool` is the platform's own Lane A number —
 * `business_id` NULL on the row, per doc 51 §10's "NULL = system/shared pool".
 */
enum NumberRole: string
{
    case Primary = 'primary';
    case Extension = 'extension';
    case SharedPool = 'shared_pool';
}
