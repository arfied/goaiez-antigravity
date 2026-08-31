<?php

declare(strict_types=1);

namespace App\Jobs\Reviews;

use App\Enums\AutopilotActionType;
use App\Exceptions\GbpRequestFailed;
use App\Jobs\AutopilotJob;
use App\Jobs\Visibility\SyncSearchConsoleJob;
use App\Models\Location;
use App\Services\Config\DefaultsRegistry;
use App\Services\Gbp\GbpConnections;
use App\Services\Gbp\GoogleReviewIngest;

/**
 * Pull one location's Google reviews into `reviews` through its GbpConnection.
 *
 * Pattern: {@see SyncSearchConsoleJob}. Idempotency lives
 * in the upsert (`location_id`, `google_review_id`); `idempotencyKey()` is null
 * so queue retries work (decision 356).
 *
 * ⚠️ Clients are resolved only through {@see GbpConnections::clientFor()} —
 * the architecture lint forbids naming GbpClient here, because a job that
 * hydrates an account ref from its own payload is exactly the hole H closed.
 */
final class SyncGoogleReviewsJob extends AutopilotJob
{
    private string $handoffReason = 'unknown';

    public function automationKey(): string
    {
        return 'reviews.google_sync';
    }

    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    protected function canExecute(): bool
    {
        $location = $this->location();

        if (! $location instanceof Location) {
            $this->handoffReason = 'location_missing';

            return false;
        }

        if (app(DefaultsRegistry::class)->value('gbp.zernio_enabled') !== true) {
            $this->handoffReason = 'integration_disabled';

            return false;
        }

        $connection = app(GbpConnections::class)->forLocation($location);

        if ($connection === null || ! $connection->isUsable()) {
            $this->handoffReason = 'not_connected';

            return false;
        }

        return true;
    }

    protected function execute(): array
    {
        $location = $this->location();

        if (! $location instanceof Location) {
            return ['outcome' => 'unavailable', 'reason' => 'location_missing'];
        }

        $connections = app(GbpConnections::class);
        $connection = $connections->forLocation($location);

        if ($connection === null || ! $connection->isUsable() || $connection->account_ref === null) {
            return ['outcome' => 'unavailable', 'reason' => 'not_connected'];
        }

        $accountRef = $connection->account_ref;
        $backfill = $connection->last_synced_at === null;
        $cursor = $backfill ? $connection->sync_cursor : null;

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $pages = 0;

        $ingest = app(GoogleReviewIngest::class);

        try {
            do {
                // ⚠️ **READ BEFORE THE CALL, NOT AFTER THE UPSERT** (7125).
                // This is the moment the provider was asked, and it is what
                // decides whether the answer is allowed to settle an unanswered
                // reply attempt. Taking it after the page had been written would
                // date the observation later than the read that produced it,
                // which is the wrong direction on the one comparison that
                // matters.
                $observedAt = now();

                $page = $connections->clientFor($connection)->reviews($accountRef, $cursor);
                $pages++;

                $counts = $ingest->upsertMany($location, $page->reviews, $observedAt);
                $inserted += $counts['inserted'];
                $updated += $counts['updated'];
                $skipped += $counts['skipped'];

                $cursor = $page->cursor;
                $connections->advanceSync($connection, $cursor, finished: false);

                if ($page->isLastPage()) {
                    break;
                }

                // Newest-first: once an incremental page inserts nothing, older
                // pages cannot hold newer reviews. Edits to older reviews arrive
                // on review.updated webhooks (539), not by walking the whole list.
                if (! $backfill && $counts['inserted'] === 0) {
                    break;
                }
            } while (true);
        } catch (GbpRequestFailed $e) {
            if ($e->disconnected) {
                $connections->refreshHealth($connection, 'system:google_sync');
                $this->handoffReason = 'connection_revoked';

                return ['outcome' => 'unavailable', 'reason' => 'connection_revoked'];
            }

            if ($e->retryable) {
                throw $e;
            }

            return ['outcome' => 'unavailable', 'reason' => $e->reason];
        }

        $connections->advanceSync($connection, null, finished: true);

        return [
            'outcome' => 'synced',
            'backfill' => $backfill,
            'pages' => $pages,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    protected function handoff(): array
    {
        $actions = [
            'not_connected' => 'connect_google',
            'connection_revoked' => 'connect_google',
            'integration_disabled' => 'none',
        ];

        return [
            'outcome' => 'handoff',
            'reason' => $this->handoffReason,
            'owner_action' => $actions[$this->handoffReason] ?? 'none',
        ];
    }
}
