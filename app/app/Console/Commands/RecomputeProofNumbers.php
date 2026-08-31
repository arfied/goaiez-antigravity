<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RecomputeProofNumbersJob;
use App\Models\Business;
use App\Models\User;
use App\Services\Proof\ProofNumbers;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * The sweep that keeps `28` §3.3's three numbers current for every tenant.
 *
 * ⛔ **WHAT THIS CLOSES IS NOT A MISSING FEATURE — IT IS A SAFEGUARD LEFT
 * STANDING WITH NOTHING BEHIND IT.** Decision **907** ruled that
 * `Livewire\Account\Home` must not recompute on render, because doing so *"would
 * hide a broken scheduler behind a screen that always looks right"*. The
 * scheduler was never built. `ProofNumbers::recompute()` had one caller in
 * `app/` — `FirstWeekPath::runDay7()`, once, over `'all'` — so **every owner's
 * *"This month"* has read three zeros since the screen shipped**, honestly, and
 * 1765 recorded the gap twice while it stayed open. The safeguard did its job
 * perfectly and the failure it revealed was invisible for exactly that reason.
 *
 * ## The enumeration, and why it is a walk rather than one query
 *
 * THE ENUMERATION FOLLOWS `trust:advance-first-week-paths` AND ITS SIBLINGS, AND
 * FOR THEIR REASONS. This runs outside any tenant, and `businesses` is FORCE ROW
 * LEVEL SECURITY on a policy keyed to the session tenant, so `Business::all()`
 * returns nothing — ⛔ **and dropping the global scope does not help, because
 * the policy is in the database and answers with zero rows rather than an
 * error** (569, 6181). That is the trap worth naming: the loud failure this walk
 * exists to avoid is not an exception, it is a sweep that reports *"No tenants
 * to work out"* on a full platform and looks like a quiet night. Reaching each
 * business through its owner via `owner_lookup` grants this sweep no privilege a
 * logged-in owner does not already have. Read `ReinviteDeferredReviews`'
 * docblock before changing this one.
 *
 * ⚠️ **DISPATCHED FOR EVERY BUSINESS, NOT ONLY THOSE WITH A `proof_numbers`
 * ROW** — and here the usual "filtering would need a second
 * `withoutGlobalScopes()` query for no real saving" is not even the argument.
 * **A tenant with no row is the tenant who most needs one**: that is precisely
 * the state whose Home screen reads zeros, so a candidate filter over
 * `proof_numbers` would skip everybody this command exists for.
 *
 * ## One clock decides the whole fan-out
 *
 * The period list is worked out once, here, and carried on the job — rather than
 * each worker asking `now()` for itself. A sweep that begins at 23:59 on the
 * 31st would otherwise have some tenants recomputing August and some September,
 * and the 1st-of-the-month arm below would fire for a fraction of the platform.
 * ⚠️ **A job that runs a few seconds past midnight still writes the right
 * bucket**: the period it carries is a string naming a month, not "the current
 * one", so it lands in August's row because August is what it was handed.
 *
 * ⚠️ **NO KILL-SWITCH CHECK HERE, DELIBERATELY, AND IT IS NOT THE OMISSION IT
 * LOOKS LIKE.** `AutopilotJob::killSwitchThrownFor()`'s own docblock invites a
 * sweeper to ask before enumerating — because each refusing job would otherwise
 * open a `skipped` run row per candidate. {@see RecomputeProofNumbersJob} writes
 * no run row at all, so the only thing a check here would save is the dispatch,
 * and the cost of having it is a second reading of one switch, which is
 * 2598's *"how a kill switch ends up half honoured"*. The job asks, once.
 */
#[Signature('proof:recompute')]
#[Description("Work out every tenant's proof numbers again")]
final class RecomputeProofNumbers extends Command
{
    public function handle(): int
    {
        $periods = ProofNumbers::periodsDueAt();

        $dispatched = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use ($periods, &$dispatched): void {
                foreach ($users as $user) {
                    $dispatched += $this->dispatchForOwner((int) $user->getKey(), $periods);
                }
            });

        // Never leave a security context established after a console command —
        // the PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($dispatched === 0
            ? 'No tenants to work out.'
            : "Dispatched {$dispatched} proof-number ".str('recompute')->plural($dispatched)
                .' over '.implode(', ', $periods).'.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $periods
     */
    private function dispatchForOwner(int $userId, array $periods): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by
        // the user just set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        foreach ($businessIds as $businessId) {
            RecomputeProofNumbersJob::dispatch((int) $businessId, $periods);
        }

        return $businessIds->count();
    }
}
