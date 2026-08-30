<?php

declare(strict_types=1);

namespace App\Modules\X186\Actions;

use App\Modules\X186\Models\CampaignStep;

final class CampaignCreateAction
{
    public function createCampaign(int $businessId, string $campaignId, array $steps = []): array
    {
        $created = [];
        foreach ($steps as $idx => $step) {
            $created[] = CampaignStep::create([
                'business_id' => $businessId,
                'campaign_id' => $campaignId,
                'step_number' => $idx + 1,
                'channel' => $step['channel'] ?? 'email',
                'template_name' => $step['template_name'] ?? 'drip_nudge_v1',
                'delay_days' => $step['delay_days'] ?? 2,
            ]);
        }

        return $created;
    }
}
