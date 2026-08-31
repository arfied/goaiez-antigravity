<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendRecoveryCheckInJob;
use App\Models\Business;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Services\Reviews\ReviewRouter;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * "Did we get that sorted?" — the sweep — T546 §37.3(1), wave 38 lane C
 * (10590–10609).
 *
 * ⚠️ **`reviews.fix_then_ask_enabled` IS ASKED HERE, BEFORE ANY ENUMERATION,
 * NOT ONLY INSIDE THE SENDER.** The feature switch is the CONFIRM substitute
 * `DefaultsManifest`'s own key argues for; refusing before a single business
 * is even looked at means an operator who never turned this on pays nothing
 * for it — no query per tenant, no run row, nothing.
 *
 * ⚠️ **THE ENUMERATION FOLLOWS `ReinviteDeferredReviews`, AND FOR ITS
 * REASONS.** This runs outside any tenant; `businesses` is FORCE row-level
 * secured on a policy keyed to the session tenant, so `Business::all()`
 * returns nothing, and reaching each business through its owner grants this
 * sweep no privilege a logged-in owner does not already have. Read that
 * command's docblock before changing this one.
 *
 * ⚠️ **A STILL-PAUSED OR STILL-SUSPENDED TENANT IS SKIPPED WHOLE**, on
 * `ReinviteDeferredReviews`'s own reasoning: a customer of a business we have
 * stopped for cause, or one who paused their own automations, is not the
 * moment to start a brand-new kind of message with them.
 */
#[Signature('reviews:fix-then-ask-checkins')]
#[Description('Send the fix-then-ask check-in to resolved recovery conversations whose delay has passed')]
final class OfferFixThenAskCheckIns extends Command
{
    /**
     * Conversations considered per business per sweep — `ReinviteDeferredReviews::PER_BUSINESS_LIMIT`'s
     * own precedent, so a backlog drains across sweeps rather than one sweep
     * holding an unbounded result set.
     */
    private const int PER_BUSINESS_LIMIT = 100;

    public function handle(DefaultsRegistry $defaults): int
    {
        if ($defaults->value('reviews.fix_then_ask_enabled') !== true) {
            $this->info('The fix-then-ask check-in is switched off; nothing sent.');

            return self::SUCCESS;
        }

        if (SendRecoveryCheckInJob::killSwitchThrownFor('review.fix_then_ask.checkin')) {
            $this->info('The fix-then-ask check-in is switched off; nothing sent.');

            return self::SUCCESS;
        }

        $delayDays = (int) $defaults->value('reviews.fix_then_ask_delay_days');
        $resolvedBefore = now()->subDays($delayDays);

        $offered = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$offered, $resolvedBefore): void {
                foreach ($users as $user) {
                    $offered += $this->sweepOwner((int) $user->getKey(), $resolvedBefore);
                }
            });

        Tenancy::forgetAll();

        $this->info($offered === 0
            ? 'No resolved conversations are due a check-in.'
            : "Offered {$offered} fix-then-ask ".str('check-in')->plural($offered).'.');

        return self::SUCCESS;
    }

    private function sweepOwner(int $userId, Carbon $resolvedBefore): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and
        // no tenant is established yet — ReinviteDeferredReviews' own
        // circularity, answered the same way. The database still restricts
        // this to businesses owned by the user just set.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $offered = 0;

        foreach ($businessIds as $businessId) {
            $offered += $this->sweepBusiness((int) $businessId, $resolvedBefore);
        }

        return $offered;
    }

    private function sweepBusiness(int $businessId, Carbon $resolvedBefore): int
    {
        Tenancy::set($businessId);

        if (app(TenantPause::class)->isCurrentTenantPaused()) {
            return 0;
        }

        if (app(TenantSuspension::class)->isCurrentTenantSuspended()) {
            return 0;
        }

        $conversations = app(ReviewRouter::class)->resolvedConversationsAwaitingCheckIn(
            $resolvedBefore,
            self::PER_BUSINESS_LIMIT,
        );

        $offered = 0;

        foreach ($conversations as $conversation) {
            SendRecoveryCheckInJob::dispatch($businessId, $conversation->review?->location_id, (int) $conversation->id);

            $offered++;
        }

        return $offered;
    }
}
