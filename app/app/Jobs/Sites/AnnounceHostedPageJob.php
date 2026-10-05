<?php

declare(strict_types=1);

namespace App\Jobs\Sites;

use App\Models\Location;
use App\Services\Indexing\Indexing;
use App\Services\Indexing\PageMarkup;
use App\Services\Tenant\LocationWebsite;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Tells the search engines about a page just published on a site we host (owner, 2026-10-05) — through {@see Indexing}, the one
 * writer of `indexing_submissions`, so every attempt is a row and a five-minute repeat is suppressed. The page is announced for
 * each location whose confirmed website is the page's own host (exact, `www.` included); a site no location claims is not
 * announced, because a location is what the indexing record — and the owner's location settings — are kept against.
 */
final class AnnounceHostedPageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $businessId,
        public readonly string $url,
    ) {}

    public function handle(Indexing $indexing): void
    {
        $host = LocationWebsite::hostOf($this->url);
        if ($host === '') {
            return;
        }

        Tenancy::actingAs($this->businessId, function () use ($indexing, $host): void {
            $locations = Location::query()
                ->where('business_id', $this->businessId)
                ->whereNotNull('website_url')
                ->whereNotNull('website_confirmed_at')
                ->orderBy('id')
                ->get();

            foreach ($locations as $location) {
                if (LocationWebsite::hostOf((string) $location->website_url) === $host) {
                    $indexing->announce($location, $this->url, PageMarkup::none());
                }
            }
        });
    }
}
