<?php

declare(strict_types=1);

namespace App\Modules\X172\Actions;

use App\Modules\X172\Models\PortalLink;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Str;

final class PortalLinkAction
{
    public const TTL_HOURS = 72;

    public function __construct(
        private readonly DefaultsRegistry $registry
    ) {}

    private function ttlHours(): int
    {
        return $this->registry->int('portal.link.ttl_hours');
    }

    public function handle(
        int $businessId,
        string $resourceType,
        int $resourceId,
        ?int $customerId = null,
        ?int $ttlHours = null
    ): PortalLink {
        // Deactivate older links for same resource
        $ttlHours ??= $this->ttlHours();
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
