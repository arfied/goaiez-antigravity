<?php

declare(strict_types=1);

namespace App\Modules\X218\Actions;

use App\Modules\X218\Models\InfluencerProfile;

final class InfluencerDiscoverAction
{
    public function discoverInfluencer(
        int $businessId,
        string $handle,
        string $platform = 'instagram',
        int $audienceSize = 15000,
        float $engagementRate = 4.20
    ): InfluencerProfile {
        return InfluencerProfile::create([
            'business_id' => $businessId,
            'handle' => $handle,
            'platform' => $platform,
            'audience_size' => $audienceSize,
            'engagement_rate' => $engagementRate,
        ]);
    }
}
