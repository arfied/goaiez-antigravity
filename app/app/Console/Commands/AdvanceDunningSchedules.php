<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AdvanceDunningScheduleJob;
use App\Models\Business;
use App\Models\User;
use App\Services\Billing\Dunning;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * The hourly sweep that makes `dunning_attempts.next_attempt_at` mean something.
 *
 * ⛔ **THAT COLUMN WAS WRITTEN BY `Dunning` AND READ BY NOTHING** (2590). It is
 * `CLAUDE.md`'s writerless-control failure with the arrow reversed — a *reader*
 * missing rather than a writer — and the symptom is identical: a green suite over
 * an inert feature. This command and {@see AdvanceDunningScheduleJob} are its
 * only reader.
 *
 * ## The enumeration follows its four siblings, and for their reasons
 *
 * This runs outside any tenant, and `businesses` is FORCE ROW LEVEL SECURITY on
 * a policy keyed to the session tenant — so `Business::all()` returns nothing,
 * and dropping a global scope does not help, because the policy is in the
 * database. Reaching each business through its owner via `owner_lookup` grants
 * this sweep no privilege a logged-in owner does not already have. Read
 * `ReinviteDeferredReviews` and `AdvanceFirstWeekPaths` before changing it.
 *
 * ⚠️ **UNLIKE `AdvanceFirstWeekPaths`, THIS ONE FILTERS BEFORE DISPATCHING, AND
 * THE DIFFERENCE IS NOT A CHANGE OF MIND** (2595). That command declined to
 * filter because filtering would have needed a second `withoutGlobalScopes()`
 * query on a tenant-owned table, widening a lint's allowlist for no real saving.
 * Here the filter needs no such thing: the tenant is established properly with
 * `Tenancy::actingAs()` and the read goes through the ordinary global scope. And
 * the saving is real rather than notional — a first-week tick is dispatched
 * daily for a tenant who will one day have a run row, whereas **almost no tenant
 * ever has an open dunning schedule at all**, and this sweep runs twenty-four
 * times a day.
 *
 * ⚠️ **THE FILTER IS AN OPTIMISATION AND NOT THE GUARD**, and saying otherwise
 * would be 314–316's mistake. `Dunning::advanceIfDue()` re-asks the same question
 * inside its transaction, under the lock on the business row. Delete this
 * `isDue()` call and the behaviour is unchanged; delete the one inside
 * `advanceIfDue()` and a double-run escalates twice.
 *
 * ⚠️ **NO GATEWAY FILTER, DELIBERATELY.** The obvious narrowing is to enumerate
 * `authorize_net_customers` instead — it is platform-scoped, readable with no
 * tenant, and is by construction the only population that can have a schedule,
 * because `Dunning::open()` is reachable only from an Authorize.Net notification
 * that resolved a tenant through that very index. It was refused because the
 * "by construction" is a claim about today's callers rather than a property of
 * the schema, and the failure mode of getting it wrong is a tenant whose
 * schedule is **never** picked up again — invisible, and indistinguishable from
 * the defect this command exists to fix.
 */
#[Signature('billing:advance-dunning')]
#[Description('Advance every dunning schedule whose next attempt has come round')]
final class AdvanceDunningSchedules extends Command
{
    public function handle(Dunning $dunning): int
    {
        $dispatched = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use ($dunning, &$dispatched): void {
                foreach ($users as $user) {
                    $dispatched += $this->dispatchForOwner((int) $user->getKey(), $dunning);
                }
            });

        // Never leave a security context established after a console command —
        // the PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($dispatched === 0
            ? 'No dunning schedules are due.'
            : "Dispatched {$dispatched} dunning ".str('tick')->plural($dispatched).'.');

        return self::SUCCESS;
    }

    private function dispatchForOwner(int $userId, Dunning $dunning): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by
        // the user just set, so it cannot widen beyond one person's own.
        $businesses = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->get();

        $dispatched = 0;

        foreach ($businesses as $business) {
            $due = Tenancy::actingAs(
                $business->id,
                fn (): bool => $dunning->isDue($business),
            );

            if (! $due) {
                continue;
            }

            AdvanceDunningScheduleJob::dispatch($business->id);
            $dispatched++;
        }

        return $dispatched;
    }
}
