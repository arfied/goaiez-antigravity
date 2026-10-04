<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Domain\SiteDesignEngines;
use App\Modules\X103\Jobs\SiteDesignJob;
use App\Modules\X103\Models\Page;
use Illuminate\Support\Carbon;

/**
 * Starts the AI designer for one page with one or more AIs: marks each running and queues one job per AI (a whole page
 * outlasts a web request). An AI already running on this page for less than ten minutes is left alone; an older run is
 * treated as dead (a worker restart can lose it), so the owner can start again.
 */
final class SiteDesignRequestAction
{
    /**
     * @param  list<string>  $engines
     * @return array{status: string, reason?: string, engines?: list<string>}
     */
    public function handle(int $businessId, int $pageId, array $engines, bool $proposeWhenReady = false): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];

        $queued = [];
        foreach (array_values(array_unique($engines)) as $engine) {
            if (! isset(SiteDesignEngines::ENGINES[$engine])) {
                continue;
            }
            $design = $meta['designs'][$engine] ?? null;
            $requestedAt = is_array($design) && is_string($design['requested_at'] ?? null) ? Carbon::parse($design['requested_at']) : null;
            if (is_array($design) && ($design['status'] ?? null) === 'running' && $requestedAt !== null && $requestedAt->gt(now()->subMinutes(10))) {
                continue;
            }
            $meta['designs'][$engine] = ['status' => 'running', 'requested_at' => now()->toIso8601String()];
            $queued[] = $engine;
        }

        if ($queued === []) {
            return ['status' => 'refused', 'reason' => 'already_running'];
        }

        $page->draft_meta = $meta;
        $page->save();

        foreach ($queued as $engine) {
            SiteDesignJob::dispatch($businessId, $pageId, $engine, proposeWhenReady: $proposeWhenReady);
        }

        return ['status' => 'queued', 'engines' => $queued];
    }
}
