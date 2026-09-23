<?php

declare(strict_types=1);

namespace App\Modules\X189\Actions;

use App\Modules\X189\Models\BrandCard;
use App\Services\Config\DefaultsRegistry;
use App\Services\Sms\TenantNumbers;

class BrandCardEnsureAction
{
    public function handle(int $businessId): BrandCard
    {
        return BrandCard::firstOrCreate(
            ['business_id' => $businessId],
            [
                'accent_color' => app(DefaultsRegistry::class)->string('brand.default_accent'),
                'badge_text' => app(TenantNumbers::class)->ownBrandNumberFor($businessId),
            ]
        );
    }
}
