<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How far through one mailbox's history this application has read.
 *
 * NOT TENANT-OWNED and carrying no tenant at all: one Workspace mailbox is the
 * shared relay every client's mail goes through (2097), so a `business_id` here
 * would assert that a mailbox belongs to a tenant. On the `TenancyTest` scope
 * allowlist with the argument written out in the creating migration.
 *
 * ⚠️ **TWO SERVICES WRITE AND READ IT AND NOBODY ELSE MAY**, held there by a
 * chokepoint lint in `MailTest`. `GmailInbox` owns the `gmail` row for the relay
 * account; `SupportMailbox` owns the `gmail-support` row for the support account
 * (T176 P24). ⛔ **THIS DOCBLOCK SAID "THE ONLY WRITER AND THE ONLY READER"
 * UNTIL 2026-08-15** — true when written, and 2505's shape the moment it was
 * not.
 *
 * ⚠️ **THE RULE IT WAS PROTECTING IS STILL THE RULE**: two writers of *one*
 * `(mailer, mailbox)` row would be two answers to *"where were we"* and
 * whichever ran first would win silently, which is how a message gets read twice
 * or never. The two services hold disjoint keys, and `SupportMailbox` refuses to
 * run when configuration would make them the same mailbox.
 *
 * @property-read int $id
 * @property string $mailer
 * @property string $mailbox
 * @property ?string $history_id
 * @property ?Carbon $watch_expires_at
 */
final class MailInboxCursor extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'watch_expires_at' => 'immutable_datetime',
        ];
    }
}
