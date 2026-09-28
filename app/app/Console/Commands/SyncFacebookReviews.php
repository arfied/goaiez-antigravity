<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Reviews\SyncFacebookReviewsJob;
use App\Models\Business;
use App\Models\User;
use App\Modules\X182\Actions\SocialAccountLookupAction;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('facebook:sync-reviews {--business= : Sync a single business by id}')]
#[Description('Pull Facebook reviews for every connected location')]
final class SyncFacebookReviews extends Command
{
    public function handle(): int
    {
        if (SyncFacebookReviewsJob::killSwitchThrownFor('reviews.facebook_sync')) {
            $this->info('Facebook review sync is switched off.');

            return self::SUCCESS;
        }

        $only = $this->option('business');

        if (is_string($only) && $only !== '') {
            $dispatched = $this->dispatchForBusiness((int) $only);

            Tenancy::forgetAll();

            $this->info('Queued '.$dispatched.' Facebook review syncs for business '.$only.'.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$dispatched): void {
                foreach ($users as $user) {
                    $dispatched += $this->dispatchForOwner((int) $user->getKey());
                }
            });

        Tenancy::forgetAll();

        $this->info('Queued '.$dispatched.' Facebook review syncs.');

        return self::SUCCESS;
    }

    private function dispatchForOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $dispatched = 0;

        foreach ($businessIds as $businessId) {
            $dispatched += $this->dispatchForBusiness((int) $businessId);
        }

        return $dispatched;
    }

    private function dispatchForBusiness(int $businessId): int
    {
        return (int) Tenancy::actingAs($businessId, function () use ($businessId): int {
            $accountIds = app(SocialAccountLookupAction::class)->syncableFacebookAccountIds();

            foreach ($accountIds as $accountId) {
                SyncFacebookReviewsJob::dispatch($businessId, (int) $accountId);
            }

            return count($accountIds);
        });
    }
}
