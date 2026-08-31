<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StoredObjectKind;

/**
 * What one kind's retention sweep did for one tenant — the sibling of
 * {@see StoredObjectTotals}, decision 4945.
 *
 * ⛔ **`skipped` IS A THIRD STATE AND NOT A ZERO, WHICH IS THE WHOLE REASON THIS
 * IS A CLASS AND NOT AN `int`.** "Nothing was old enough to delete" and "nobody
 * has said how long to keep these" are opposite facts that both produce a count
 * of nought, and only the second is something a person needs to act on. Folding
 * them together is exactly the shape `PruneTenantExports` was caught in at 1993,
 * where *"No expired exports to prune."* printed over a sweep that had refused
 * to prune anything — an affirmative statement that nothing was owed, made by a
 * run that could not tell.
 */
final readonly class StorageSweep
{
    /**
     * @param  int  $pruned  objects deleted, and their rows updated to say so
     * @param  int  $refused  objects the store would not delete — the rows are
     *                        left naming them so the next run tries again
     */
    public function __construct(
        public StoredObjectKind $kind,
        public int $pruned,
        public int $refused,
        public bool $skipped,
    ) {}

    /**
     * The sweep that did not run, because no period is set for this kind.
     *
     * ⛔ **THIS IS THE DEFAULT STATE OF EVERY KIND TODAY** (4942). Four of the
     * five have no period and therefore delete nothing at all; see
     * {@see StorageRetention} for why an unset period is the open state here and
     * the closed one on a sending ceiling.
     */
    public static function skipped(StoredObjectKind $kind): self
    {
        return new self(kind: $kind, pruned: 0, refused: 0, skipped: true);
    }
}
