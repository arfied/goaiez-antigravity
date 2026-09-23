<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Models\TenantExport;
use App\Services\Export\ExportBuilder;
use App\Services\Support\DataRequests;
use App\Support\QueueBackoff;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Build one "Download my data" ZIP, off the request (`28` §3.7).
 *
 * ⚠️ **NOT AN `AutopilotJob`, AND THE REASON IS THE SAME SHAPE AS
 * `DeliverPlatformMail`'S BUT A DIFFERENT SIDE OF IT.** That class opts out
 * because platform mail has no tenant and no automation toggle to respect; this
 * one opts out because it has a tenant and *must not* respect the two checks
 * `AutopilotJob::handle()` runs before every automation — `TenantPause` and
 * `TenantSuspension`. `28` §3.7 is explicit: *"exporting is never delayed,
 * gated on retention offers, or degraded"*, and `28` §9.5's cooling window is
 * specified *"with export offered"* — precisely the moment an account is most
 * likely to be paused or suspended. Extending `AutopilotJob` would silently
 * gate the one feature the spec singles out as ungatable, on the exact tenants
 * who need it working. This job carries no per-automation toggle and no kill
 * switch for the same reason: CLAUDE.md's "every job … gated on toggles + kill
 * switches" is written for the 142-catalog automations `AutopilotJob` runs, and
 * §3.7's more specific rule governs this one by name (decision 1827).
 *
 * ⚠️ **DELIBERATELY NOT LOCATION-SCOPED.** `28` §3.7 is account-wide — "Download
 * my data" — not per-location, so the constructor carries a business id and
 * nothing else. `RefreshOauthTokensJob` shows `AutopilotJob`'s own
 * `$locationId` is already optional for a business-wide job; this one simply
 * has no location dimension to carry at all.
 *
 * ## Idempotent by row state, not by a claimed key
 *
 * A retry after a crash mid-build re-enters {@see ExportBuilder::build()},
 * which re-assembles the ZIP and re-uploads to the *same* stable storage path —
 * harmless, because the content is deterministic from the tenant's current
 * data. What must not happen twice is the "ready" transition and the email it
 * triggers, and that guard is `build()`'s own atomic conditional update, not
 * this class's.
 *
 * ⚠️ **THIS CLASS'S OWN `status === Ready` SKIP IS AN EFFICIENCY GUARD, AND
 * SAYING OTHERWISE WOULD BE DECISIONS 314–316'S MISTAKE.** `build()`'s guard
 * alone already makes a redelivery leave the row and the notification count
 * untouched — proved by "a retried build produces one file and exactly one
 * email" — so this line changes nothing a database read can observe. What it
 * changes is work done for no reader: without it, a redelivered job after a
 * successful build silently redoes the whole assembly and re-uploads to R2
 * every time it is retried. `ExportBuilder` is `final`, which rules out a
 * call-count mock, so the proof in `tests/Feature/ExportBuilderTest.php` reads
 * the *S3 object itself* — a forced time gap makes the manifest's
 * `generated_at` move if a second assembly genuinely runs, and it does not.
 */
final class BuildTenantExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Three attempts, the same ladder {@see DeliverPlatformMail} uses: a
     * transient failure (storage unreachable) is worth retrying, and a
     * configuration error will not fix itself on the third try either way.
     */
    public int $tries = 3;

    public function backoff(): array
    {
        return QueueBackoff::ladder(QueueBackoff::fromSetting('queue.backoff.standard_seconds'));
    }

    public function __construct(
        public readonly int $businessId,
        public readonly int $tenantExportId,
    ) {}

    public function handle(ExportBuilder $builder, DataRequests $dataRequests): void
    {
        // A job dispatched from the scheduler or a redelivery inherits no
        // tenant context at all — AutopilotJob's own reasoning, restated here
        // because this job does not extend it.
        Tenancy::set($this->businessId);

        $export = TenantExport::query()->find($this->tenantExportId);

        if (! $export instanceof TenantExport || $export->status === ExportStatus::Ready) {
            return;
        }

        $builder->build($export);

        // Closes the ops queue's row when this build was approved through it.
        // A no-op for the owner's own button, which links no request at all —
        // see DataRequests::noteExportOutcome()'s own docblock for why this
        // call lives here and not inside ExportBuilder.
        $dataRequests->noteExportOutcome($export->refresh());
    }

    /**
     * Every retry exhausted. Marks the row rather than leaving it `queued`
     * forever with no explanation on it.
     */
    public function failed(?Throwable $exception): void
    {
        Tenancy::set($this->businessId);

        $export = TenantExport::query()->find($this->tenantExportId);

        if (! $export instanceof TenantExport) {
            return;
        }

        app(ExportBuilder::class)->fail(
            $export,
            'Could not be built after 3 attempts: '.($exception?->getMessage() ?? 'unknown error'),
        );

        app(DataRequests::class)->noteExportOutcome($export->refresh());
    }
}
