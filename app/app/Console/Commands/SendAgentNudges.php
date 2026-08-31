<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendAgentNudgeJob;
use App\Models\AgentNudge;
use App\Models\Business;
use App\Models\User;
use App\Services\Agent\AgentNudges;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Send the follow-up owed to threads that went quiet — T176 skill 14, P11.
 *
 * ## Why a sweep rather than a delayed dispatch
 *
 * ⚠️ **`SendAgentNudgeJob::dispatch()->delay(4 hours)` IS THE OBVIOUS SHAPE AND
 * IS WRONG HERE**, for `SendInviteReminders`' reasons, all of which apply
 * unchanged: a payload sitting on the queue cannot be re-decided, it carries a
 * decision made before the customer replied and before the owner took the thread
 * over, and **every refusal on this path is temporary** — a closed daytime
 * window, a platform halt, a tenant pause, an exhausted balance. A sweep asks
 * again on the next pass, which is what makes *"held rather than sent"* true.
 * ⛔ **And the nudge has a hard 24-hour outside edge**, so a job that could only
 * answer once would silently turn every one of those holds into a follow-up that
 * never happened.
 *
 * ## The enumeration
 *
 * ⚠️ **FOLLOWS `reviews:remind`, AND FOR ITS REASONS**, which are
 * `oauth:refresh-tokens`'. This runs outside any tenant; `businesses` is FORCE
 * ROW LEVEL SECURITY on a policy keyed to the session tenant, so `Business::all()`
 * returns nothing and dropping a global scope does not help, because the policy
 * is in the database. Reaching each business through its owner grants this sweep
 * no privilege a logged-in owner does not already have.
 *
 * ⛔ **NOTHING STARVES, BECAUSE `expires_at` IS ON THE ROW.** `SendInviteReminders`
 * needed a rolling candidate window to stop a permanently-refused candidate
 * sitting at the head of an ascending queue for ever; here the window is a column
 * the sweep itself closes, with a reason, so a nudge that could never go stops
 * being a candidate within a day whether or not anything was sent.
 */
#[Signature('agent:nudge')]
#[Description('Send the one follow-up owed to conversations that went quiet after a link')]
final class SendAgentNudges extends Command
{
    /**
     * Nudges considered per business per sweep.
     *
     * `SendInviteReminders::PER_BUSINESS_LIMIT`'s figure and its reasoning: a cap
     * rather than a chunked walk, so a backlog drains across sweeps.
     */
    private const int PER_BUSINESS_LIMIT = 100;

    public function handle(): int
    {
        // Asked before anything is enumerated, on `SendInviteReminders`'
        // reasoning: each job would refuse individually and correctly, but only
        // after opening a `skipped` run row, so a killed automation would write
        // one row per candidate every time the schedule fired.
        if (SendAgentNudgeJob::killSwitchThrownFor('agent.nudge')) {
            $this->info('Assistant follow-ups are switched off; nothing sent.');

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
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($dispatched === 0
            ? 'No assistant follow-ups are due.'
            : "Queued {$dispatched} assistant ".str('follow-up')->plural($dispatched).'.');

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     */
    private function sweepOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by the
        // user just set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $dispatched = 0;

        foreach ($businessIds as $businessId) {
            $dispatched += $this->sweepBusiness((int) $businessId);
        }

        return $dispatched;
    }

    private function sweepBusiness(int $businessId): int
    {
        // Inside the tenant from here down. Every query below is an ordinary
        // scoped Eloquent query with RLS beneath it.
        Tenancy::set($businessId);

        // ⚠️ A PAUSED OR SUSPENDED TENANT IS SKIPPED WHOLE, on
        // `SendInviteReminders`' reasoning: each job would refuse individually
        // and correctly, and what this buys is silence rather than correctness.
        // ⛔ **Nothing is lost by waiting only because `expires_at` is what gives
        // up** — a pause longer than a day closes these nudges with a reason on
        // the next sweep after it lifts, rather than sending a day-old follow-up.
        if (app(TenantPause::class)->isCurrentTenantPaused()) {
            return 0;
        }

        if (app(TenantSuspension::class)->isCurrentTenantSuspended()) {
            return 0;
        }

        $dispatched = 0;

        foreach (app(AgentNudges::class)->due()->take(self::PER_BUSINESS_LIMIT) as $nudge) {
            // ⚠️ **NO PRE-FILTERING OF THE REST HERE, AND THAT IS DELIBERATE.**
            // The toggle, the thread status, the reply check, the window and the
            // permit are all asked inside the job, where the answer is written
            // onto a run row and — for the terminal ones — onto the nudge itself
            // as a reason. Asking them here as well would make the sweep the
            // place a follow-up quietly stops happening, with nothing recorded.
            SendAgentNudgeJob::dispatch(
                $businessId,
                $this->locationOf($nudge),
                (int) $nudge->getKey(),
            );

            $dispatched++;
        }

        return $dispatched;
    }

    private function locationOf(AgentNudge $nudge): ?int
    {
        $locationId = $nudge->location_id;

        return is_numeric($locationId) ? (int) $locationId : null;
    }
}
