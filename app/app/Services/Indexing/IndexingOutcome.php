<?php

declare(strict_types=1);

namespace App\Services\Indexing;

/**
 * What one call to {@see Indexing::announce()} did.
 *
 * ⚠️ **`suppressed` IS COUNTED RATHER THAN INVISIBLE.** A lane skipped inside
 * the cooldown wrote no row on purpose — the row from a moment ago says the same
 * thing — but a caller that could not tell the difference between *"we
 * announced this"* and *"we already had"* would report the second as the first.
 */
final readonly class IndexingOutcome
{
    /**
     * @param  list<IndexingAttempt>  $attempts  One per lane actually run.
     * @param  int  $suppressed  Lanes inside the cooldown, which left the
     *                           existing row alone.
     */
    public function __construct(
        public array $attempts,
        public int $suppressed = 0,
    ) {}
}
