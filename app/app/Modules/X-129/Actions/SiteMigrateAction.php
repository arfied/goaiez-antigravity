<?php

declare(strict_types=1);

namespace App\Modules\X129\Actions;

use App\Modules\X129\Events\DomainVerified;
use App\Modules\X129\Events\SiteMigrated;
use App\Modules\X129\Models\RedirectMap;
use Illuminate\Support\Facades\Event;

final class SiteMigrateAction
{
    /**
     * Cutover is BLOCKED while any old URL returns 404 on the new host (TEST ANCHOR).
     */
    public function cutover(int $businessId, string $domain, array $sourceCrawlUrls, array $simulatedHostResponses = []): array
    {
        $missing404s = [];

        // 1. Verify every URL has a redirect and returns 200/301, not 404 (TEST ANCHOR)
        foreach ($sourceCrawlUrls as $url) {
            $redirect = RedirectMap::where('business_id', $businessId)->where('source_url', $url)->first();

            $statusCode = $simulatedHostResponses[$url] ?? ($redirect ? 301 : 404);

            if ($statusCode === 404 || ! $redirect) {
                $missing404s[] = $url;
            }
        }

        if (! empty($missing404s)) {
            return [
                'status' => 'refused',
                'refusal_code' => 'CUTOVER_BLOCKED_UNRESOLVED_404_URLS',
                'message' => 'Cutover is blocked while any old URL returns 404 on the new host',
                'blocked_urls' => $missing404s,
                'migrated' => false,
            ];
        }

        $count = RedirectMap::where('business_id', $businessId)->count();

        Event::dispatch(new SiteMigrated($businessId, $domain, $count));
        Event::dispatch(new DomainVerified($businessId, $domain));

        return [
            'status' => 'cutover_completed',
            'domain' => $domain,
            'redirects_verified' => $count,
            'migrated' => true,
        ];
    }
}
