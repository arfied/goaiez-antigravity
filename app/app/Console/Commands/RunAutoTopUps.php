<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AutoTopUpArrangement;
use App\Models\Business;
use App\Models\User;
use App\Services\Billing\AutoTopUps;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Charge the accounts whose balances have fallen low enough, under arrangements
 * they agreed to.
 *
 * ⛔ **THIS COMMAND SPENDS OTHER PEOPLE'S MONEY WITH NOBODY WATCHING**, which is
 * what makes it different from every other sweep in this codebase. Every refusal
 * lives in {@see AutoTopUps::refusalFor()} rather than here — a condition written
 * into a console command is one no test drives directly and one the next caller
 * will not know about.
 *
 * ⚠️ **AN ACCOUNT WITH NO ARRANGEMENT IS NEVER TOUCHED.** The sweep reads live
 * arrangements, and an account that has never opted in has no row at all (3306,
 * and the owner's "disabled by default"). There is deliberately no "and also
 * check whether they'd like to" branch: the absence of a row is the off state.
 *
 * ## Why a daily schedule rather than a reaction to the send that ran low
 *
 * Firing from inside the send path would put a card charge on the hot path of a
 * text message, where a gateway timeout becomes a failed send. **The trade is
 * that a tenant who exhausts a balance mid-campaign waits for the sweep** — which
 * is why the thresholds fire well before zero rather than at it.
 */
#[Signature('credits:run-auto-top-ups')]
#[Description('Charge accounts whose balances have fallen below their automatic top-up threshold.')]
final class RunAutoTopUps extends Command
{
    public function __construct(private readonly AutoTopUps $autoTopUps = new AutoTopUps)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $charged = 0;
        $refused = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$charged, &$refused): void {
                foreach ($users as $user) {
                    [$c, $r] = $this->sweepOwner((int) $user->getKey());

                    $charged += $c;
                    $refused += $r;
                }
            });

        // Never leave a security context established after a console command. The
        // PostgreSQL session variable outlives this process's connection under any
        // pooler, and a worker inheriting it would start as whichever tenant the
        // loop happened to touch last.
        Tenancy::forgetAll();

        $this->info(
            "Topped up {$charged} ".str('account')->plural($charged)
            .", left {$refused} ".str('arrangement')->plural($refused).' alone.'
        );

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @return array{int, int} charged, refused
     */
    private function sweepOwner(int $userId): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity `ResolveTenant` and
        // `ResetMonthlyCredits` both document. The database still restricts this
        // to businesses owned by the user just set, so it cannot widen beyond one
        // person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $charged = 0;
        $refused = 0;

        foreach ($businessIds as $businessId) {
            [$c, $r] = $this->sweepBusiness((int) $businessId);

            $charged += $c;
            $refused += $r;
        }

        return [$charged, $refused];
    }

    /**
     * One account's arrangements, across whichever products it has agreed for.
     *
     * @return array{int, int} charged, refused
     */
    private function sweepBusiness(int $businessId): array
    {
        // Inside the tenant from here down. Every query below is an ordinary
        // scoped Eloquent query with RLS beneath it.
        Tenancy::set($businessId);

        if (! Business::query()->whereKey($businessId)->exists()) {
            return [0, 0];
        }

        $charged = 0;
        $refused = 0;

        foreach ($this->autoTopUps->liveArrangements() as $arrangement) {
            // ⚠️ ASKED BEFORE CHARGING SO THE REASON CAN BE REPORTED, and asked
            // again inside `chargeIfNeeded()` because a service that only refuses
            // when its caller remembers to ask is not a service that refuses.
            $refusal = $this->autoTopUps->refusalFor($arrangement);

            if ($refusal !== null) {
                $this->line($this->describe($arrangement).' — '.$refusal);
                $refused++;

                continue;
            }

            if ($this->autoTopUps->chargeIfNeeded($arrangement) !== null) {
                $charged++;

                continue;
            }

            // The charge was attempted and did not happen — a declined card or a
            // price that outran the agreement. `AutoTopUps` has already recorded
            // why on the arrangement.
            $refused++;
        }

        return [$charged, $refused];
    }

    private function describe(AutoTopUpArrangement $arrangement): string
    {
        return 'Account '.$arrangement->business_id.' ('.$arrangement->product->value.')';
    }
}
