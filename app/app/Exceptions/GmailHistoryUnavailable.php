<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Gmail will not tell us what changed since the point we asked from.
 *
 * ⚠️ **THIS IS A DOCUMENTED ORDINARY OUTCOME, NOT A VENDOR OUTAGE**, and the
 * distinction decides whether the job retries or gives up. Google's own
 * reference for `users.history.list` (read 2026-08-12, page dated 2026-04-15):
 * *"Supplying an invalid or out of date `startHistoryId` typically returns an
 * HTTP 404 error code"*, and a `historyId` is only guaranteed valid *"for at
 * least a week, though occasionally only hours"*.
 *
 * ⛔ **RETRYING IT IS THE WRONG ANSWER AND WOULD LOOP FOR EVER.** The cursor
 * cannot become valid again by waiting; it can only be abandoned. So
 * `IngestGmailPushJob` catches this, moves the bookmark to the current mailbox
 * position, and **says out loud that a gap exists** — because the honest
 * consequence is that any reply that arrived inside the gap is unreachable, and
 * a silent reset would look exactly like a quiet mailbox.
 */
final class GmailHistoryUnavailable extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function cursorTooOld(string $cursor): self
    {
        return new self(
            "Gmail no longer holds history from record {$cursor}. Google keeps a mailbox's "
            .'history for about a week, so a bookmark older than that is refused with a 404 and '
            .'cannot be recovered by retrying. The bookmark is moved to the current position and '
            .'anything that arrived in between is unreadable.'
        );
    }
}
