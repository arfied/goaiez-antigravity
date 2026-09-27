<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\CustomDomainRequest;
use App\Modules\X157\Models\EdgeZone;
use App\Services\Widgets\WidgetPlugins;

/**
 * Every host the platform serves this business's pages on.
 *
 * This feeds the pixel gate and the Pixel screen. It is shared by every tenant with a site there,
 * so a leaked pixel key works from any tenant's page on it.
 */
final class SiteHostsAction
{
    /**
     * @return list<string>
     */
    public function handle(int $businessId): array
    {
        $platformDomains = EdgeZone::query()
            ->where('business_id', $businessId)
            ->where('provider', 'platform')
            ->where('has_valid_ssl', true)
            ->pluck('domain_name');

        $customDomains = CustomDomainRequest::query()
            ->where('business_id', $businessId)
            ->where('status', 'verified')
            ->pluck('domain');

        $hosts = [];

        foreach ($platformDomains->concat($customDomains) as $domain) {
            $host = WidgetPlugins::normaliseHost((string) $domain);
            if ($host !== null) {
                $hosts[] = $host;
            }
        }

        $hosts = array_values(array_unique($hosts));
        sort($hosts);

        return $hosts;
    }
}
