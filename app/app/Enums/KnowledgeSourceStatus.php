<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How far a knowledge source has got (DATA-MODEL §5.9).
 *
 * ⚠️ **`Failed` AND `Unreadable` ARE DIFFERENT ANSWERS AND THE OWNER NEEDS THE
 * DIFFERENCE.** One is ours — a vendor outage, an exhausted cap, a queue that
 * never drained — and it clears on its own when the work is retried. The other
 * is theirs: we could not get words out of the file they chose, and no amount of
 * waiting fixes it. Collapsing the two into one `failed` would tell somebody
 * whose scanned PDF will never work to sit tight, which is the worst available
 * answer because it costs them a support ticket a week later.
 *
 * A string column cast to this enum, never a database enum (`CLAUDE.md`).
 */
enum KnowledgeSourceStatus: string
{
    /** Uploaded and queued. The migration's own column default. */
    case Pending = 'pending';

    /** A worker has it. Set inside the job so a stuck source is visible as stuck. */
    case Ingesting = 'ingesting';

    /** Chunked, embedded and stored. The Brain can retrieve from it. */
    case Ingested = 'ingested';

    /** Something on our side went wrong. Retryable — see the class docblock. */
    case Failed = 'failed';

    /** We could not read words out of it. Not retryable; the owner must act. */
    case Unreadable = 'unreadable';

    /**
     * Outcome language (`22`), and never the mechanism.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting to be read',
            self::Ingesting => 'Being read',
            self::Ingested => 'Ready to answer from',
            self::Failed => 'Could not finish — we will try again',
            self::Unreadable => 'We could not read this file',
        };
    }

    /**
     * Whether this source is done moving.
     *
     * Used by the screen to decide whether to keep polling, and by the ingest
     * job's own idempotency reasoning: a terminal source is not re-read.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Ingested, self::Unreadable => true,
            self::Pending, self::Ingesting, self::Failed => false,
        };
    }
}
