<?php

declare(strict_types=1);

namespace App\Modules\X138\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class RoiComputeAction
{
    public function computeCampaignRoi(int $businessId, string $campaignName, int $adSpendCents, int $closedRevenueCents): array
    {
        $now = Carbon::now();

        $snapshotId = DB::table('roi_snapshots')->insertGetId([
            'business_id' => $businessId,
            'campaign_name' => $campaignName,
            'ad_spend_cents' => $adSpendCents,
            'closed_revenue_cents' => $closedRevenueCents,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roiMultiple = ($adSpendCents > 0) ? round($closedRevenueCents / $adSpendCents, 2) : 0.0;

        return [
            'snapshot_id' => $snapshotId,
            'campaign_name' => $campaignName,
            'ad_spend_cents' => $adSpendCents,
            'closed_revenue_cents' => $closedRevenueCents,
            'roi_multiple' => $roiMultiple,
        ];
    }
}
