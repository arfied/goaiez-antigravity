<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use App\Services\Content\ContentSelfAudit;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Doc `16` §15.3's daily self-audit, on its clock.
 *
 * ⚠️ **A COMMAND RATHER THAN AN `AutopilotJob`, ON `ProbeLocationSiteJob`'s
 * OPPOSITE REASONING (5555).** This one is not an automation acting on a
 * tenant's behalf — it is the platform checking whether it should keep acting at
 * all, and it must keep running for a tenant whose account is paused, because a
 * paused tenant's pages are still on their website and still accumulating
 * whatever Google thinks of them. Making it a gated automation would switch off
 * the audit exactly when publishing was already stopped, so the pause could
 * never be lifted.
 *
 * ⚠️ **IT WRITES NOTHING TO ANY WEBSITE AND MAKES ONE READ-ONLY VENDOR CALL PER
 * CONNECTED LOCATION.** `GoogleSearchConsoleClient` is `webmasters.readonly` by
 * construction (1083) and 5480 forbids looking for a submission path; this slice
 * added a second read on a different dimension and no new scope.
 */
#[Signature('content:self-audit {--business= : Audit a single business by id}')]
#[Description('Flag near-duplicate and zero-impression published pages, and pause generation on a breach')]
final class AuditPublishedContent extends Command
{
    public function handle(): int
    {
        $only = $this->option('business');

        if (is_string($only) && $only !== '') {
            $paused = $this->auditBusiness((int) $only);

            Tenancy::forgetAll();

            $this->info('Audited business '.$only.'; '.$paused.' locations paused.');

            return self::SUCCESS;
        }

        $paused = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$paused): void {
                foreach ($users as $user) {
                    $paused += $this->auditOwner((int) $user->getKey());
                }
            });

        Tenancy::forgetAll();

        $this->info('Audit finished; '.$paused.' locations paused.');

        return self::SUCCESS;
    }

    private function auditOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $paused = 0;

        foreach ($businessIds as $businessId) {
            $paused += $this->auditBusiness((int) $businessId);
        }

        return $paused;
    }

    private function auditBusiness(int $businessId): int
    {
        return (int) Tenancy::actingAs($businessId, function (): int {
            $audit = app(ContentSelfAudit::class);

            $now = CarbonImmutable::now();

            $paused = 0;

            foreach (Location::query()->orderBy('id')->get() as $location) {
                if ($audit->run($location, $now) !== []) {
                    $paused++;
                }
            }

            return $paused;
        });
    }
}
