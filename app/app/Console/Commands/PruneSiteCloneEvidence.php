<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SiteCloneJob;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Prune clone directories older than the retention horizon.
 *
 * A clone directory holds the Claude stream, the rendered pages, screenshots of a
 * third-party site and the bundle — an operational artefact nobody chose to keep,
 * so (as `PruneFailedJobs` argues) the horizon is a constant in code, not an
 * unset registry row; the row in `site_clone_jobs` is history and stays.
 */
#[Signature('site-clone:prune-evidence')]
#[Description('Prune clone directories older than the retention horizon')]
final class PruneSiteCloneEvidence extends Command
{
    /**
     * How long a clone evidence directory is kept.
     *
     * A clone directory holds the Claude stream, the rendered pages, screenshots of a
     * third-party site and the bundle — an operational artefact nobody chose to keep,
     * so (as PruneFailedJobs argues) the horizon is a constant in code, not an
     * unset registry row; the row in site_clone_jobs is history and stays.
     */
    public const int RETENTION_DAYS = 7;

    public function handle(): int
    {
        $deleted = 0;
        $refused = 0;
        $horizon = CarbonImmutable::now()->subDays(self::RETENTION_DAYS);
        $root = (string) config('site_clone.root');

        $businessIds = DB::table('businesses')->pluck('id');

        foreach ($businessIds as $businessId) {
            Tenancy::actingAs($businessId, function () use (&$deleted, &$refused, $horizon, $root) {
                $jobs = DB::table('site_clone_jobs')
                    ->whereIn('status', [SiteCloneJob::DONE, SiteCloneJob::FAILED, SiteCloneJob::CANCELLED])
                    ->whereNotNull('finished_at')
                    ->where('finished_at', '<', $horizon)
                    ->whereNotNull('work_dir')
                    ->get();

                foreach ($jobs as $job) {
                    $workDir = $job->work_dir;

                    if (! str_starts_with($workDir, $root)) {
                        $refused++;

                        continue;
                    }

                    if (is_dir($workDir)) {
                        File::deleteDirectory($workDir);
                    }

                    DB::table('site_clone_jobs')
                        ->where('id', $job->id)
                        ->update(['work_dir' => null]);

                    $deleted++;
                }
            });
        }

        if ($refused > 0) {
            $this->warn("Refused {$refused} clone evidence ".str('directory')->plural($refused).' outside the clone root.');
        }

        $this->info($deleted === 0
            ? 'No clone evidence older than '.self::RETENTION_DAYS.' days to prune.'
            : "Pruned {$deleted} clone evidence ".str('directory')->plural($deleted)
                .' older than '.self::RETENTION_DAYS.' days.');

        return self::SUCCESS;
    }
}
