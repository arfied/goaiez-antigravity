<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\CustomDomainRequest;
use App\Modules\X157\Models\EdgeZone;

class CustomDomainStatusAction
{
    public function handle(int $businessId): array
    {
        $platformZone = app(PlatformSiteAddressAction::class)->handle($businessId);
        $request = CustomDomainRequest::where('business_id', $businessId)->first();
        $zone = EdgeZone::where('business_id', $businessId)->where('provider', '!=', 'platform')->first();

        return [
            'platform_address' => $platformZone->domain_name,
            'requested_domain' => $request?->domain,
            'requested_at' => $request?->requested_at,
            'zone_domain' => $zone?->domain_name,
            'has_valid_ssl' => $zone ? $zone->has_valid_ssl : false,
        ];
    }
}
