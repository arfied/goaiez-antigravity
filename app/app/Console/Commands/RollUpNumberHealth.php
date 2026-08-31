<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AutopilotJob;
use App\Jobs\NumberHealthRollup;
use App\Models\Business;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Services\Sms\NumberHealthService;
use App\Services\Sms\NumberHealthSignals;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fan the hourly number health recompute out across every number — row 4
 * slice 6 phase 2, doc `51` §4.4, §11.
 *
 * ⚠️ **TWO PATHS, AND THEY ARE NOT SYMMETRIC.** A tenant-owned number is one
 * dispatch of {@see NumberHealthRollup}, `RefreshOauthTokens`'s shape exactly:
 * one job, one tenant, one number, all of `AutopilotJob`'s machinery applies
 * for free. The shared Lane A pool number cannot go through that job at all —
 * see {@see NumberHealthRollup}'s own docblock for why — so this command
 * computes its rollup **directly**, walking every business through its owner
 * the same way `RefreshOauthTokens`/`ReanalyseReviews` already do, for the
 * identical reason: `businesses` is FORCE ROW LEVEL SECURITY on the session
 * tenant, so there is no query that lists every business, and reaching each
 * one through `owner_lookup` grants this sweep no privilege a logged-in owner
 * does not already have.
 *
 * ⚠️ **THE SHARED NUMBER'S OUTREACH TOTAL IS SUMMED ACROSS TENANTS; ITS STOP
 * AND REPLY TOTAL IS READ ONCE.** `outreach_messages` is tenant-owned and
 * RLS-`FORCE`d, so a tenant's contribution to the shared number's sends has to
 * be gathered from inside that tenant's own context and added up. `inbound_
 * messages` carries no tenant at all, so its STOP/reply counts for the shared
 * number are the same number regardless of which tenant is current — reading
 * them inside the same per-tenant loop would multiply a shared number's true
 * STOP count by however many tenants share it.
 */
#[Signature('numbers:health-rollup')]
#[Description('Recompute today\'s number_health_daily row and refresh phone_numbers.health_score')]
final class RollUpNumberHealth extends Command
{
    public function handle(NumberHealthService $health): int
    {
        // Checked once, before anything is enumerated. A tenant-owned number
        // dispatched below would refuse individually and correctly inside
        // AutopilotJob — but only after opening a `skipped` run row per
        // number, and the shared pool number below goes through no
        // AutopilotJob at all to refuse through.
        if (AutopilotJob::killSwitchThrownFor('numbers.health_rollup')) {
            $this->info('Number health rollup is switched off; nothing recomputed.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        PhoneNumber::query()
            ->whereNotNull('business_id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $numbers) use (&$dispatched): void {
                foreach ($numbers as $number) {
                    NumberHealthRollup::dispatch((int) $number->business_id, null, (int) $number->id);
                    $dispatched++;
                }
            });

        $recomputed = 0;

        foreach (PhoneNumber::query()->whereNull('business_id')->get() as $number) {
            $this->rollUpSharedNumber($number, $health);
            $recomputed++;
        }

        // Never leave a security context established after a console command
        // — the same rule RefreshOauthTokens states at the identical point.
        Tenancy::forgetAll();

        $this->info(
            "Queued {$dispatched} tenant-owned rollup(s); recomputed {$recomputed} shared-pool number(s) directly."
        );

        return self::SUCCESS;
    }

    private function rollUpSharedNumber(PhoneNumber $number, NumberHealthService $health): void
    {
        $now = CarbonImmutable::now();
        $todayStart = $now->startOfDay();
        $rollingStart = $now->subDay();

        [$todayOutreach, $rollingOutreach] = $this->outreachAcrossTenants($number, $health, $todayStart, $rollingStart, $now);

        $today = $todayOutreach->add($health->inboundCounts($number->e164, $todayStart, $now));
        $rolling = $rollingOutreach->add($health->inboundCounts($number->e164, $rollingStart, $now));

        $reason = $health->recompute($number, $today, $rolling);

        if ($reason === null) {
            return;
        }

        // ⛔ **THE LOG RATHER THAN THE ACTIVITY FEED, AND 1630's WALL IS WHY**
        // (3792, which amends 3775's "what makes it visible is nothing").
        // `NumberHealthRollup` files an `OwnerActionNeeded` item for a
        // tenant-owned number; `activity_feed` is tenant-owned and the shared
        // Lane A number belongs to nobody, so there is no tenant to file this
        // under — and filing it under every tenant that happens to share the
        // number would put one automatic event in a hundred owners' histories.
        //
        // ⚠️ **AND THIS IS THE ONE THAT STOPS EVERY TEXT ON THE PLATFORM**, so
        // it is the line somebody has to see. The number is ours, not a
        // customer's, which is what makes it printable — `NumberLifecycle` makes
        // the same argument at the identical point.
        //
        // ⚠️ **THE OPPOSITE EVENT — A QUARANTINE *WITHHELD* — DOES NOT COME
        // THROUGH HERE, AND SAYING SO IS THE POINT** (10080). When yesterday's
        // rollup row was never written, `NumberHealthService` refuses to
        // evaluate the STOP trigger at all rather than dividing in a one-day
        // window, and announces the refusal itself, because it is the only
        // thing that can see the missing row. `$reason` is null on that path,
        // so this method is silent — **which is correct and is not the same as
        // nothing having happened.** Read `NumberHealthService::stopWindow()`
        // before reading a quiet run here as a healthy pool.
        $this->warn(sprintf('%s was quarantined automatically: %s', $number->e164, $reason));

        Log::warning('The shared sending number was quarantined automatically.', [
            'e164' => $number->e164,
            'reason' => $reason,
        ]);
    }

    /**
     * Every tenant's contribution to the shared number's outreach totals, for
     * both windows in one pass — `ReanalyseReviews`'s enumeration, reused.
     *
     * @return array{0: NumberHealthSignals, 1: NumberHealthSignals} today, then rolling 24h
     */
    private function outreachAcrossTenants(
        PhoneNumber $number,
        NumberHealthService $health,
        CarbonImmutable $todayStart,
        CarbonImmutable $rollingStart,
        CarbonImmutable $now,
    ): array {
        $today = new NumberHealthSignals;
        $rolling = new NumberHealthSignals;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$today, &$rolling, $number, $health, $todayStart, $rollingStart, $now): void {
                foreach ($users as $user) {
                    Tenancy::setUser((int) $user->getKey());

                    // withoutGlobalScopes because the scope calls
                    // Tenancy::idOrFail() and no tenant is established yet —
                    // the same circularity ResolveTenant documents. The
                    // database still restricts this to businesses owned by
                    // the user just set.
                    $businessIds = Business::withoutGlobalScopes()
                        ->where('owner_user_id', $user->getKey())
                        ->pluck('id');

                    foreach ($businessIds as $businessId) {
                        Tenancy::actingAs((int) $businessId, function () use (&$today, &$rolling, $number, $health, $todayStart, $rollingStart, $now): void {
                            $today = $today->add($health->outreachCounts($number->id, $todayStart, $now));
                            $rolling = $rolling->add($health->outreachCounts($number->id, $rollingStart, $now));
                        });
                    }
                }
            });

        return [$today, $rolling];
    }
}
