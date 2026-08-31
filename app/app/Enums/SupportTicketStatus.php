<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where one support request stands — T137 `SL-7`.
 *
 * Three states rather than a ticketing system's usual seven. Each one answers a
 * question somebody actually asks: *has anyone here replied yet* (Open →
 * Answered), and *is this finished* (Resolved). A `pending`, an `on hold` and a
 * `reopened` are states an agent maintains rather than states a tenant reads,
 * and every one of them is a support surface `CLAUDE.md` says to refuse until
 * somebody asks for it.
 *
 * ⚠️ **`Answered` DOES NOT MEAN FINISHED**, and the label says so out loud. A
 * thread the tenant has replied to again goes back to `Open` — the queue's
 * subject is *work waiting on us*, so the state has to move backwards when the
 * ball does.
 */
enum SupportTicketStatus: string
{
    /** Waiting on us. */
    case Open = 'open';

    /** We have replied and nobody has come back. */
    case Answered = 'answered';

    /** Closed by the tenant or by us. */
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Waiting for us',
            self::Answered => 'We replied',
            self::Resolved => 'Closed',
        };
    }

    /**
     * Whether this thread is still live — the queue's own predicate, written
     * once so a screen never re-derives it from a `!== Resolved` comparison
     * that a fourth case would silently falsify.
     */
    public function isLive(): bool
    {
        return $this !== self::Resolved;
    }
}
