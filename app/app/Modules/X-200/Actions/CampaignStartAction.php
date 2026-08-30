<?php

declare(strict_types=1);

namespace App\Modules\X200\Actions;

use App\Modules\X200\Models\CallCampaign;
use InvalidArgumentException;

final class CampaignStartAction
{
    /**
     * Starts dialer campaign.
     * Hard maximum 3.0% abandonment ceiling, lower only; UI & API reject higher (G3-04, G10-03, §160.1).
     */
    public function startCampaign(int $businessId, string $name, float $abandonmentCeilingPct = 3.00): CallCampaign
    {
        if ($abandonmentCeilingPct > 3.00) {
            throw new InvalidArgumentException('Campaign rejected: abandonment ceiling cannot exceed 3.0% (§160.1, G10-03)');
        }

        return CallCampaign::create([
            'business_id' => $businessId,
            'name' => $name,
            'abandonment_ceiling_pct' => $abandonmentCeilingPct,
            'is_running' => true,
        ]);
    }
}
