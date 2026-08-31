<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Content\PublishGrowthPageJob;
use App\Models\Business;
use App\Models\User;
use App\Services\Content\GrowthPages;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The clock AUTO-WITH-HOLD runs on (`29` §4.5).
 *
 * *"Owner is told; proceeds unless they reply STOP within the window."* Nothing
 * proceeds on its own — a `hold_until` in the past is a row, not an event — so
 * this is the thing that turns a closed window into a publication.
 *
 * ⛔ **A HELD PAGE WITH NO RELEASE TIME IS NEVER SWEPT** (5565).
 * {@see GrowthPages::dueForRelease()} writes that predicate out rather than
 * relying on SQL's answer for a null comparison, because the two holds are
 * indistinguishable on every column except that one: the gate's hold and the
 * owner's STOP both wait for a person, and publishing either would be publishing
 * exactly the page somebody refused.
 *
 * ⚠️ **IT DISPATCHES RATHER THAN PUBLISHES.** The job carries the kill switch,
 * the tenant pause, the suspension and the idempotency claim, and a command that
 * did the work itself would be a second path past all four — 398's shape with the
 * guards on the other side.
 */
#[Signature('content:release-holds {--business= : Sweep a single business by id}')]
#[Description('Publish growth pages whose 24-hour hold window has closed')]
final class ReleaseContentHolds extends Command
{
    public function handle(): int
    {
        // Asked once here rather than per page: each job would refuse correctly
        // and would open a `skipped` run row first, so a switched-off automation
        // would write a row per held page every hour (823's reasoning).
        if (PublishGrowthPageJob::killSwitchThrownFor('content.publish_growth_page')) {
            $this->info('Publishing is switched off.');

            return self::SUCCESS;
        }

        $only = $this->option('business');

        if (is_string($only) && $only !== '') {
            $dispatched = $this->sweep((int) $only);

            Tenancy::forgetAll();

            $this->info('Queued '.$dispatched.' releases for business '.$only.'.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$dispatched): void {
                foreach ($users as $user) {
                    $dispatched += $this->sweepOwner((int) $user->getKey());
                }
            });

        // Never leave a security context established after a console command:
        // the PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop touched last.
        Tenancy::forgetAll();

        $this->info('Queued '.$dispatched.' releases.');

        return self::SUCCESS;
    }

    private function sweepOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet. The database still restricts this to
        // businesses owned by the user just set.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $dispatched = 0;

        foreach ($businessIds as $businessId) {
            $dispatched += $this->sweep((int) $businessId);
        }

        return $dispatched;
    }

    private function sweep(int $businessId): int
    {
        return (int) Tenancy::actingAs($businessId, function () use ($businessId): int {
            $pages = app(GrowthPages::class);

            $dispatched = 0;

            foreach ($pages->dueForRelease(Carbon::now()) as $pageId) {
                $candidate = $pages->candidate($pageId);

                if ($candidate === null) {
                    continue;
                }

                PublishGrowthPageJob::dispatch($businessId, $candidate->locationId, $pageId);

                $dispatched++;
            }

            return $dispatched;
        });
    }
}
