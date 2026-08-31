<?php

declare(strict_types=1);

namespace App\Enums;

use App\Console\Commands\PruneStoredObjects;
use App\Support\TableHorizons;

/**
 * What a table's horizon actually removes — decision 8001.
 *
 * ⛔ **THIS EXISTS BECAUSE "THAT TABLE HAS A PRUNER" IS TRUE OF FOUR TABLES
 * WHOSE ROWS ARE PERMANENT.** `storage:prune` deletes the *object* an
 * `inbound_media`, `voicemails`, `knowledge_sources` or `campaign_recipients`
 * row names and then rewrites the row to stop naming it —
 * {@see PruneStoredObjects}. The bytes in the bucket go; the row, its
 * `business_id`, its `customer_id` and its timestamps stay for ever. A list of
 * table names that answered *"pruned: yes"* for those four would be exactly the
 * protection claim `CLAUDE.md` 314-316 is about: a reader who sees a horizon
 * beside the table stops looking for one.
 *
 * ⚠️ **AND THE DISTINCTION RUNS THE OTHER WAY TOO.** A rows horizon takes the
 * bytes with it and needs no separate byte story; a bytes horizon leaves a row
 * count that only ever rises, so a table with one is still an unbounded *row*
 * count on every screen and in every `count(*)`.
 *
 * A string cast to a PHP backed enum, never a database enum - `CLAUDE.md`. It
 * backs no column: the backing values exist so {@see TableHorizons} and the
 * command that prints it agree on one spelling.
 */
enum RetentionScope: string
{
    /** The row is deleted. Its bytes go with it. */
    case Rows = 'rows';

    /**
     * The object the row names is deleted and the row is rewritten to stop
     * naming it. **The row itself is never removed by anything on a clock.**
     */
    case Bytes = 'bytes';

    /**
     * What an operator reads beside a table on the footprint report.
     *
     * Outcome language (`22`): what happens to the thing, never the mechanism
     * that does it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Rows => 'rows deleted',
            self::Bytes => 'bytes only, rows kept',
        };
    }
}
