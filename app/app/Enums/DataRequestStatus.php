<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Export\ExportBuilder;

/**
 * Where a privacy request sits in the ops queue (`28` §9.5).
 *
 * ## ⚠️ `Fulfilled` MEANS DELIVERED, AND FOR ONE KIND IT USED TO MEAN BUILT
 *
 * A tenant-export ask closed as `Fulfilled` the moment
 * {@see ExportBuilder::build()} finished (1999). That word asserts a delivery
 * this system cannot make and cannot observe: `announce()` mails the owner
 * through `PlatformMailer`, the relay vendor is **not picked** (1191–1195) and
 * bounce handling is unbuilt (open question H) — so the email is effectively a
 * no-op today and the in-app link is the whole delivery. Combined with 1905's
 * refusal (an agent may not fetch the ZIP even for an approved request), an ops
 * agent approved a statutory subject-access request, watched the row say
 * *Done*, and **had no way to tell whether the tenant ever received it**.
 *
 * {@see self::Built} is the honest intermediate. The row stays on the open
 * queue where an agent can see it, and the first successful fetch is what
 * closes it — which also makes `export.downloaded` visible where somebody is
 * already looking, rather than only in `audit_log`.
 *
 * ⚠️ **THE ALTERNATIVE WAS CONSIDERED AND IS WEAKER.** Surfacing the
 * `export.downloaded` entry on the queue row alone leaves the status word
 * lying, and `open()` drops `Fulfilled` rows — so under the old flow there was
 * no queue row left to surface it on. Fixing the word fixes both.
 */
enum DataRequestStatus: string
{
    /** Filed, waiting on a second person or on fulfilment. */
    case Open = 'open';

    /**
     * Erasure: the cooling window is running (second person confirmed).
     * Consent audit: reserved; not used — audits fulfill in one step.
     */
    case AwaitingDeletion = 'awaiting_deletion';

    /**
     * Tenant export: the ZIP exists and the owner has been told; nobody has
     * fetched it yet.
     *
     * ⚠️ **NOT CLOSED**, deliberately — see the enum docblock. A request whose
     * artifact nobody has collected is not answered, and this is the state that
     * says so out loud instead of asserting a delivery nothing observed.
     */
    case Built = 'built';

    /** Done — trail produced, account destroyed, or honest refusal recorded. */
    case Fulfilled = 'fulfilled';

    /** Could not be done; reason on the row. */
    case Refused = 'refused';

    /** Operator withdrew it before fulfilment. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::AwaitingDeletion => 'Waiting on the cooling window',
            self::Built => 'Ready — waiting for the tenant to collect it',
            self::Fulfilled => 'Done',
            self::Refused => 'Refused',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isClosed(): bool
    {
        return match ($this) {
            self::Fulfilled, self::Refused, self::Cancelled => true,
            self::Open, self::AwaitingDeletion, self::Built => false,
        };
    }
}
