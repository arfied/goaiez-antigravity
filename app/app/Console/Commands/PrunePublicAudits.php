<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PublicAudit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes public audits past their 90-day retention window (decision 192).
 *
 * This exists in the same slice as `expires_at` on purpose. A retention column
 * that nothing ever acts on is not a retention policy — it is a claim, and the
 * table it describes is a permanent record of businesses that never signed up
 * and never asked to be audited. The privacy answer to "how long do you keep
 * this?" has to be enforced by something.
 *
 * A hard delete rather than a soft one. There is no erasure story that ends
 * with the row still present, and nothing downstream references it: slice H
 * copies an audit's text into `wizard_progress.data` rather than pointing at
 * it, precisely so that this sweep never leaves a dangling reference.
 *
 * Chunked because a slow day's backlog should not be one long transaction on a
 * table the public path also writes to.
 */
#[Signature('audits:prune')]
#[Description('Delete public audits past their retention window (90 days)')]
final class PrunePublicAudits extends Command
{
    /**
     * Rows per DELETE. Large enough that a normal day is one or two statements,
     * small enough that no single statement holds locks for long.
     */
    private const int CHUNK = 500;

    public function handle(): int
    {
        $deleted = 0;

        do {
            $batch = PublicAudit::query()
                ->expired()
                ->limit(self::CHUNK)
                ->delete();

            $deleted += $batch;
        } while ($batch === self::CHUNK);

        $this->info($deleted === 0
            ? 'No expired audits to prune.'
            : "Pruned {$deleted} expired ".str('audit')->plural($deleted).'.');

        return self::SUCCESS;
    }
}
