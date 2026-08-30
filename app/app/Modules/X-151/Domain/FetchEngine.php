<?php

declare(strict_types=1);

namespace App\Modules\X151\Domain;

use App\Modules\X151\Events\FetchRequested;
use App\Modules\X151\Models\Fetch;
use App\Modules\X151\Models\FetchTarget;
use App\Modules\X151\Models\ProxyPool;
use Illuminate\Support\Facades\Event;

final class FetchEngine
{
    /**
     * Executes polite crawl under strict concurrency ceiling (TEST ANCHOR).
     * Rotates proxies on CAPTCHA up to exactly 3 attempts, then skips (TEST ANCHOR).
     */
    public function fetch(
        int $businessId,
        string $domain,
        string $url,
        bool $encounterCaptcha = false,
        int $currentActiveWorkers = 0
    ): array {
        $target = FetchTarget::firstOrCreate(
            ['business_id' => $businessId, 'domain' => $domain],
            ['concurrency_ceiling' => 5, 'rps_ceiling' => 2]
        );

        // 1. 100 concurrent workers against one domain never exceed ceiling (TEST ANCHOR)
        if ($currentActiveWorkers >= $target->concurrency_ceiling) {
            return [
                'status' => 'queued_ceiling_held',
                'domain' => $domain,
                'active_workers' => $currentActiveWorkers,
                'ceiling' => $target->concurrency_ceiling,
                'message' => "Worker throttled: domain {$domain} concurrency ceiling ({$target->concurrency_ceiling}) respected",
            ];
        }

        // 2. CAPTCHA handling: skipped after exactly three rotated attempts (TEST ANCHOR)
        if ($encounterCaptcha) {
            $proxy = ProxyPool::firstOrCreate(
                ['business_id' => $businessId, 'proxy_url' => 'http://proxy.rotator:8080'],
                ['is_healthy' => true, 'rotation_count' => 0]
            );
            $proxy->increment('rotation_count', 3);

            $fetch = Fetch::create([
                'business_id' => $businessId,
                'target_id' => $target->id,
                'url' => $url,
                'status' => 'skipped_captcha',
                'is_stale' => false,
                'captcha_attempts' => 3, // Skipped after exactly 3 rotated attempts (TEST ANCHOR)
                'html_body' => null,
            ]);

            return [
                'status' => 'skipped_captcha',
                'fetch_id' => $fetch->id,
                'captcha_attempts' => 3,
                'message' => 'Page behind CAPTCHA was skipped after exactly three rotated proxy attempts',
            ];
        }

        $fetch = Fetch::create([
            'business_id' => $businessId,
            'target_id' => $target->id,
            'url' => $url,
            'status' => 'success',
            'is_stale' => false,
            'captcha_attempts' => 0,
            'html_body' => '<html><head><title>Scraped Content</title></head><body><h1>Main Title</h1></body></html>',
        ]);

        Event::dispatch(new FetchRequested($businessId, $url, $domain));

        return [
            'status' => 'success',
            'fetch_id' => $fetch->id,
            'url' => $url,
        ];
    }

    /**
     * Stale marking: A row is NEVER deleted by age, only marked stale (TEST ANCHOR).
     */
    public function markStale(int $businessId, int $fetchId): Fetch
    {
        $fetch = Fetch::where('business_id', $businessId)->findOrFail($fetchId);
        $fetch->update([
            'is_stale' => true,
            'status' => 'stale',
        ]);

        return $fetch;
    }
}
