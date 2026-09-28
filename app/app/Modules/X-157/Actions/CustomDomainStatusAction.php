<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\CustomDomainRequest;

class CustomDomainStatusAction
{
    public function handle(int $businessId): array
    {
        $platformZone = app(PlatformSiteAddressAction::class)->handle($businessId);
        $request = CustomDomainRequest::where('business_id', $businessId)->orderByDesc('requested_at')->orderByDesc('id')->first();

        return [
            'platform_address' => $platformZone->domain_name,
            'requested_domain' => $request?->domain,
            'requested_at' => $request?->requested_at,
            'status' => $request?->status,
            'verified_at' => $request?->verified_at,
            'last_checked_at' => $request?->last_checked_at,
            'failure_reason' => $request?->failure_reason,
        ];
    }
}
