<?php

declare(strict_types=1);

namespace App\Modules\X200\Actions;

use App\Modules\X200\Models\CallCampaign;

final class CampaignPauseAction
{
    public function pauseCampaign(int $businessId, int $campaignId): CallCampaign
    {
        $campaign = CallCampaign::where('business_id', $businessId)->findOrFail($campaignId);
        $campaign->update(['is_running' => false]);

        return $campaign;
    }
}
