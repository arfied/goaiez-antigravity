<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Actuation\JudgeSpeedFixJob;
use App\Models\Business;
use App\Models\User;
use App\Services\Actuation\SpeedFixes;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * The clock `28` §4.3 runs on — *"p75 … over 7 days vs the 14-day pre-change
 * baseline"*.
 *
 * ⛔ **A SWEEP RATHER THAN A DELAYED DISPATCH, ON `MeasureSiteChanges`'
 * ARGUMENT (5815).** A job queued with a week's delay is a promise held in
 * Redis: nobody can list what is owed, a queue flush loses every judgement
 * silently, and a fix the owner undid on day two still has a job waiting to
 * judge it. The rows are the state and the queue is only a courier.
 *
 * ⛔ **IT DISPATCHES RATHER THAN JUDGES.** {@see JudgeSpeedFixJob} carries the
 * pause and the suspension, and a command doing the work here would be a second
 * path past both — 398's shape with the guards on the other side.
 *
 * ⚠️ **ONE DUE SET, WHERE SLICE H's SWEEP HAS TWO**, and that is not an
 * omission: the retry for a revert that did not land is
 * `SiteMeasurements::dueForRevert()`, which selects on
 * `site_changes.verdict = regressed` and therefore already carries speed fixes.
 * Building a second retry here would be two things asking the same website for
 * the same undo on the same night.
 */
#[Signature('actuation:judge-speed-fixes {--business= : Sweep a single business by id}')]
#[Description('Judge speed fixes whose seven-day window has closed, and reverse the ones that did not help')]
final class JudgeSpeedFixes extends Command
{
    public function handle(): int
    {
        $only = $this->option('business');

        if (is_string($only) && $only !== '') {
            $dispatched = $this->sweep((int) $only);

            Tenancy::forgetAll();

            $this->info('Queued '.$dispatched.' speed judgements for business '.$only.'.');

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

        $this->info('Queued '.$dispatched.' speed judgements.');

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
            $fixes = app(SpeedFixes::class);

            $dispatched = 0;

            foreach ($fixes->dueForJudgement(CarbonImmutable::now()) as $id) {
                $subject = $fixes->subject($id);

                if ($subject === null) {
                    continue;
                }

                JudgeSpeedFixJob::dispatch($businessId, $subject->locationId, $id);

                $dispatched++;
            }

            return $dispatched;
        });
    }
}
