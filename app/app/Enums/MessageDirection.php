<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which way one message in a thread travelled (DATA-MODEL §5.9).
 *
 * ⚠️ **`messages.direction` HAS BEEN A BARE STRING SINCE 2026-07-30 AND HAD NO
 * WRITER UNTIL P18.** CLAUDE.md's rule is a `string` column cast to a backed
 * enum in `app/Enums`, never a database `enum` — the column was already the
 * right shape and the PHP half was simply never written, because nothing wrote
 * a row. It is written here rather than left as a literal, because the Inbox
 * renders the two sides differently and a screen comparing `=== 'inbound'` is
 * one typo away from showing the customer's words as the business's own.
 */
enum MessageDirection: string
{
    /** The customer wrote it. */
    case Inbound = 'inbound';

    /** This platform sent it, on the tenant's behalf or as the tenant. */
    case Outbound = 'outbound';

    /**
     * Whether this message came from the person the thread is with.
     *
     * ⚠️ **THE ONLY PLACE THE QUESTION IS ANSWERED.** The Inbox uses it to
     * decide attribution and whose words are being shown, and a caller
     * comparing cases by hand is how one of them disagrees with the others.
     */
    public function isFromCustomer(): bool
    {
        return $this === self::Inbound;
    }
}
