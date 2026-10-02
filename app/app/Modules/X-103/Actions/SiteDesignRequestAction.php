<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Jobs\SiteDesignJob;
use App\Modules\X103\Models\Page;
use Illuminate\Support\Carbon;

/** Starts the AI designer for one page: marks it running and queues the job (a whole page outlasts a web request). */
final class SiteDesignRequestAction
{
    /**
     * @return array{status: string, reason?: string}
     */
    public function handle(int $businessId, int $pageId): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        $design = $page->draft_meta['design'] ?? null;
        $requestedAt = is_array($design) && is_string($design['requested_at'] ?? null) ? Carbon::parse($design['requested_at']) : null;
        // A run older than ten minutes is treated as dead (a worker restart can lose it), so the owner can start again.
        if (is_array($design) && ($design['status'] ?? null) === 'running' && $requestedAt !== null && $requestedAt->gt(now()->subMinutes(10))) {
            return ['status' => 'refused', 'reason' => 'already_running'];
        }

        $meta = $page->draft_meta ?? [];
        $meta['design'] = ['status' => 'running', 'requested_at' => now()->toIso8601String()];
        $page->draft_meta = $meta;
        $page->save();

        SiteDesignJob::dispatch($businessId, $pageId);

        return ['status' => 'queued'];
    }
}
