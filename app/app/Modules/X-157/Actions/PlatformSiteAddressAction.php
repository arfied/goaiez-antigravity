<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\EdgeZone;

class PlatformSiteAddressAction
{
    /**
     * The page is served from this application's own host under its own certificate; nothing here claims a vendor zone.
     */
    public function handle(int $businessId): EdgeZone
    {
        $appUrl = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        return EdgeZone::updateOrCreate(
            ['business_id' => $businessId, 'provider' => 'platform'],
            [
                'domain_name' => $appUrl,
                'zone_id' => 'platform_'.$businessId,
                'has_valid_ssl' => true,
                'ssl_certificate_id' => 'platform-certificate',
                'status' => 'active',
            ]
        );
    }
}
