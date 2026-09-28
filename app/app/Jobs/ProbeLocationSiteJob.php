<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Location;
use App\Services\Actuation\SiteProbe;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\QueueBackoff;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Look at one confirmed website, off the request.
 *
 * ⚠️ **NOT AN `AutopilotJob`, AND `BuildTenantExportJob`'s DOCBLOCK IS THE SHAPE
 * OF THE ARGUMENT** (5546). That class is the contract for the 142 catalog
 * automations: `automationKey()` names a row in `16`, `execute()`/`handoff()` are
 * rule 44's two paths, and every run opens an `automation_runs` row an owner can
 * be shown. **This is not an automation.** It performs one read of the tenant's
 * own website, changes nothing anywhere, produces nothing an owner acts on, and
 * has no provider-free variant to hand off to — a `handoff()` here could only be
 * an empty method, which is the stub rule 44 exists to forbid.
 *
 * ⚠️ **IT KEEPS TWO OF THAT CLASS'S GATES ANYWAY, BY HAND AND ON PURPOSE.** A
 * paused or suspended tenant gets no outbound request made on their behalf, even
 * a harmless one: row 5's pause is *"all sending + actuation off"*, and a fetch
 * of their website while their account is held is a request their host sees with
 * our user agent on it.
 *
 * ## Idempotent because it is a read
 *
 * Running twice looks at the site twice and writes the same three columns to the
 * same values, `wordpress_detected_at` included — {@see SiteProbe::probe()} keeps
 * the original timestamp while the finding holds. So there is no key to claim and
 * nothing a duplicate can corrupt; what a retry costs is one fetch against a rate
 * budget the gateway already counts.
 */
final class ProbeLocationSiteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Three attempts with backoff: a website that was down a minute ago is the
     * ordinary reason this fails, and it is the ordinary reason to try again.
     */
    public int $tries = 3;

    public function backoff(): array
    {
        return QueueBackoff::ladder(QueueBackoff::fromSetting('queue.backoff.media_seconds'));
    }

    /**
     * ⚠️ **IDS RATHER THAN MODELS.** `SerializesModels` re-queries on unserialize
     * with no tenant in context, so a `Location` property resolves to nothing and
     * the job fails for a reason that names neither the tenant nor the row.
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $locationId,
    ) {}

    public function handle(SiteProbe $probe, TenantPause $pause, TenantSuspension $suspension): void
    {
        Tenancy::set($this->businessId);

        if ($suspension->isCurrentTenantSuspended() || $pause->isCurrentTenantPaused()) {
            return;
        }

        $location = Location::query()->find($this->locationId);

        // Deleted, or belonging to another tenant — in which case the global
        // scope has already made it invisible and this is the same outcome.
        if (! $location instanceof Location) {
            return;
        }

        $probe->probe($location);
    }
}
