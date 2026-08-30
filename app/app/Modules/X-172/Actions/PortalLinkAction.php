<?php

declare(strict_types=1);

namespace App\Modules\X172\Actions;

use App\Modules\X172\Models\PortalLink;
use Illuminate\Support\Str;

final class PortalLinkAction
{
    public function handle(
        int $businessId,
        string $resourceType,
        int $resourceId,
        ?int $customerId = null,
        int $ttlHours = 72
    ): PortalLink {
        // Deactivate older links for same resource
        PortalLink::where('business_id', $businessId)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->update(['is_active' => false]);

        return PortalLink::create([
            'business_id' => $businessId,
            'customer_id' => $customerId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'token' => Str::random(40),
            'expires_at' => now()->addHours($ttlHours),
            'is_active' => true,
        ]);
    }
}
