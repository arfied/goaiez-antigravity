<?php

declare(strict_types=1);

namespace App\Services\Indexing;

use App\Contracts\IndexNowKeys;
use App\Models\Location;
use App\Modules\X157\Actions\SiteHostsAction;
use App\Services\Tenant\LocationWebsite;

/**
 * Where a location's IndexNow key file lives when its website is a site THIS PLATFORM HOSTS (owner, 2026-10-05).
 *
 * A location whose confirmed website address is a custom domain verified to point at us (X-157) is served by this application,
 * so the key file `/{key}.txt` exists at that host's root — X-157's provider answers it on every host. That is exactly the
 * file decision 5581 says core REST cannot put on a WordPress site, and here nothing needs to put it anywhere.
 *
 * Every other location gets {@see UnhostedIndexNowKeys}'s answer unchanged: a WordPress site still has nothing serving the
 * file, and announcing to it would earn the 403 that reads like a vendor outage.
 *
 * The host match is exact, `www.` included — {@see LocationWebsite::hostOf()}'s rule: `www.` is a different host.
 */
final class HostedIndexNowKeys implements IndexNowKeys
{
    public function __construct(
        private readonly UnhostedIndexNowKeys $unhosted,
        private readonly SiteHostsAction $hosts,
    ) {}

    public function for(Location $location): IndexNowKeyOutcome
    {
        if ($location->website_url !== null && $location->website_confirmed_at !== null) {
            $host = LocationWebsite::hostOf($location->website_url);
            if ($host !== '' && in_array($host, $this->hosts->handle((int) $location->business_id), true)) {
                return IndexNowKeyOutcome::found(IndexNowKey::of($host, self::key(), 'https://'.$host.'/'.self::key().'.txt'));
            }
        }

        return $this->unhosted->for($location);
    }

    /**
     * The platform's one key, the same on every host it serves: derived one-way from the application key, so nothing is stored
     * and nothing can be read back from it. IndexNow keys are public by design (the file is fetched by anyone).
     */
    public static function key(): string
    {
        return substr(hash('sha256', 'indexnow|'.(string) config('app.key')), 0, 32);
    }
}
