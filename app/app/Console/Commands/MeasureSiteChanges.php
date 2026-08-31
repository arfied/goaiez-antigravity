<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Actuation\MeasureSiteChangeJob;
use App\Models\Business;
use App\Models\User;
use App\Services\Actuation\SiteMeasurements;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * The clock `29` §2 rule 32's *"measure 14–30 days"* runs on.
 *
 * ⛔ **A SWEEP RATHER THAN A DELAYED DISPATCH AT PUBLISH TIME, AND THE CHOICE IS
 * NOT STYLISTIC.** A job queued with a thirty-day delay is a promise held in
 * Redis: nobody can list what is owed, a queue flush loses every measurement
 * silently, and a change reverted by its owner in week two still has a job
 * waiting to judge it. A sweep re-derives the due set from the rows every
 * morning, so the rows are the state and the queue is only a courier.
 *
 * ⛔ **IT DISPATCHES RATHER THAN MEASURES.** {@see MeasureSiteChangeJob} carries
 * the tenant pause and the suspension, and a command that did the work here
 * would be a second path past both — 398's shape with the guards on the other
 * side. `ReleaseContentHolds` makes the same argument for the same reason.
 *
 * ⚠️ **TWO DUE SETS, AND THE SECOND IS THE ONE NOBODY WOULD PREDICT.** Changes
 * whose window has closed and which have never been measured, **and** changes
 * already measured as a regression that are still on the site because the revert
 * failed. Without the second, one unreachable website leaves our own harmful
 * change on it for ever (`SiteMeasurements::dueForRevert()`).
 */
#[Signature('actuation:measure-site-changes {--business= : Sweep a single business by id}')]
#[Description('Judge site changes whose 14–30 day measurement window has closed, and revert regressions')]
final class MeasureSiteChanges extends Command
{
    public function handle(): int
    {
        $only = $this->option('business');

        if (is_string($only) && $only !== '') {
            $dispatched = $this->sweep((int) $only);

            Tenancy::forgetAll();

            $this->info('Queued '.$dispatched.' measurements for business '.$only.'.');

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

        $this->info('Queued '.$dispatched.' measurements.');

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
            $measurements = app(SiteMeasurements::class);

            // ⚠️ **ONE CLOCK FOR BOTH DUE SETS** (6265). The revert arm now has a
            // backoff to compare against, and two `now()` calls a microsecond
            // apart would make the two halves of one sweep disagree about when
            // it ran — the shape 5969 records for the judge's own window.
            $now = CarbonImmutable::now();

            // ⚠️ **EACH DUE SET IS BOUNDED AND THE REMAINDER IS LEFT** (6055,
            // 6265). `SiteMeasurements::SWEEP_LIMIT` rows apiece, ordered by id,
            // so a tenant with a large backlog spreads over nights rather than
            // dispatching a job per row in one burst at a customer's website.
            $due = array_unique([
                ...$measurements->dueForMeasurement($now),
                ...$measurements->dueForRevert($now),
            ]);

            $dispatched = 0;

            foreach ($due as $changeId) {
                $subject = $measurements->subject($changeId);

                if ($subject === null) {
                    continue;
                }

                MeasureSiteChangeJob::dispatch($businessId, $subject->locationId, $changeId);

                $dispatched++;
            }

            return $dispatched;
        });
    }
}
