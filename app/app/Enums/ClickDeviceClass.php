<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How coarse a bucket a clicking device falls into.
 *
 * ⚠️ **THREE BUCKETS AND AN UNKNOWN, DELIBERATELY.** `CLAUDE.md` permits device
 * signals *"for bot scoring and device-class bucketing only — never concatenated
 * into a stable identifier"*, and the way that rule gets broken is never by one
 * decision: it is by a fourth field, then a fifth, each individually harmless,
 * until the row identifies a handset. **The defence is that there is nothing
 * here to combine** — one value out of four, stored beside a timestamp and a
 * boolean, is not a fingerprint at any width.
 *
 * ⛔ **THE USER-AGENT STRING ITSELF IS NEVER STORED.** It is read once, bucketed,
 * and dropped. A raw agent string is high-entropy and pairs with a timestamp to
 * follow one person across two links, which is the thing the ban is about.
 */
enum ClickDeviceClass: string
{
    case Phone = 'phone';
    case Tablet = 'tablet';
    case Desktop = 'desktop';

    /**
     * No usable signal. ⚠️ **Not a failure and not rare** — plenty of clients
     * send nothing useful, and a bucket that pretended to know would be worse
     * than one that says it does not.
     */
    case Unknown = 'unknown';
}
