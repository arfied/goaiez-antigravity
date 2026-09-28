<?php

declare(strict_types=1);

namespace App\Jobs\Reviews;

use App\Enums\AutopilotActionType;
use App\Exceptions\GbpRequestFailed;
use App\Jobs\AutopilotJob;
use App\Models\Location;
use App\Models\Review;
use App\Modules\X182\Models\SocialAccount;
use App\Services\Config\DefaultsRegistry;
use App\Services\Reviews\FacebookReviewIngest;
use App\Services\Zernio\ZernioSocialClient;

final class SyncFacebookReviewsJob extends AutopilotJob
{
    private string $handoffReason = 'unknown';

    public function __construct(int $businessId, public readonly int $socialAccountId)
    {
        parent::__construct($businessId);
    }

    public function automationKey(): string
    {
        return 'reviews.facebook_sync';
    }

    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    protected function canExecute(): bool
    {
        $account = SocialAccount::find($this->socialAccountId);

        if ($account === null || $account->platform !== 'facebook' || $account->status !== 'connected' || $account->account_ref === null || $account->location_id === null) {
            $this->handoffReason = 'not_connected';

            return false;
        }

        if (app(DefaultsRegistry::class)->value('social.zernio_enabled') !== true) {
            $this->handoffReason = 'integration_disabled';

            return false;
        }

        return true;
    }

    protected function execute(): array
    {
        $account = SocialAccount::find($this->socialAccountId);

        if ($account === null || $account->platform !== 'facebook' || $account->status !== 'connected' || $account->account_ref === null || $account->location_id === null) {
            return ['outcome' => 'unavailable', 'reason' => 'not_connected'];
        }

        $location = Location::find($account->location_id);

        if (! $location instanceof Location) {
            return ['outcome' => 'unavailable', 'reason' => 'location_missing'];
        }

        $inserted = 0;
        $updated = 0;
        $unrated = 0;
        $pages = 0;

        $client = app(ZernioSocialClient::class);
        $ingest = app(FacebookReviewIngest::class);

        $cursor = null;

        $firstSync = ! Review::where('location_id', $location->id)
            ->where('source', 'facebook')
            ->exists();

        try {
            do {
                $page = $client->facebookReviews($account->account_ref, $cursor, 50);
                $pages++;

                $counts = $ingest->upsertPage($location, $page);
                $inserted += $counts['inserted'];
                $updated += $counts['updated'];
                $unrated += $counts['unrated'];

                $cursor = $page->nextCursor;

                if ($cursor === null) {
                    break;
                }

                if (! $firstSync && $counts['inserted'] === 0) {
                    break;
                }
            } while (true);
        } catch (GbpRequestFailed $e) {
            if ($e->disconnected) {
                $this->handoffReason = 'connection_revoked';

                return ['outcome' => 'unavailable', 'reason' => 'connection_revoked'];
            }

            if ($e->retryable) {
                throw $e;
            }

            return ['outcome' => 'unavailable', 'reason' => $e->reason];
        }

        return [
            'outcome' => 'synced',
            'pages' => $pages,
            'inserted' => $inserted,
            'updated' => $updated,
            'unrated' => $unrated,
        ];
    }

    protected function handoff(): array
    {
        $actions = [
            'not_connected' => 'connect_facebook',
            'connection_revoked' => 'connect_facebook',
            'integration_disabled' => 'none',
        ];

        return [
            'outcome' => 'handoff',
            'reason' => $this->handoffReason,
            'owner_action' => $actions[$this->handoffReason] ?? 'none',
        ];
    }
}
