<?php

declare(strict_types=1);

namespace App\Modules\X166\Actions;

use App\Modules\X166\Events\JobCosted;
use App\Modules\X166\Events\MarginBelowThreshold;
use App\Modules\X166\Models\JobCost;
use Illuminate\Support\Facades\Event;

final class JobCostAction
{
    public function handle(
        int $businessId,
        int $jobId,
        string $priceBookVersion,
        int $laborCostCents,
        int $materialsCostCents,
        int $overheadCostCents,
        int $revenueCents,
        ?int $techId = null,
        string $serviceType = 'general',
        string $source = 'inbound_call'
    ): JobCost {
        $totalCostCents = $laborCostCents + $materialsCostCents + $overheadCostCents;
        $grossMarginCents = $revenueCents - $totalCostCents;
        $grossMarginPct = ($revenueCents > 0) ? round(($grossMarginCents / $revenueCents) * 100, 2) : 0.0;

        $cost = JobCost::create([
            'business_id' => $businessId,
            'job_id' => $jobId,
            'price_book_version' => $priceBookVersion, // TEST ANCHOR: every cost row cites the pricebook version
            'labor_cost_cents' => $laborCostCents,
            'materials_cost_cents' => $materialsCostCents,
            'overhead_cost_cents' => $overheadCostCents,
            'total_cost_cents' => $totalCostCents,
            'revenue_cents' => $revenueCents,
            'gross_margin_cents' => $grossMarginCents,
            'gross_margin_pct' => $grossMarginPct,
            'tech_id' => $techId,
            'service_type' => $serviceType,
            'source' => $source,
        ]);

        Event::dispatch(new JobCosted(
            businessId: $businessId,
            jobId: $jobId,
            priceBookVersion: $priceBookVersion,
            grossMarginCents: $grossMarginCents,
            grossMarginPct: $grossMarginPct
        ));

        if ($grossMarginPct < 20.0) {
            Event::dispatch(new MarginBelowThreshold(
                businessId: $businessId,
                jobId: $jobId,
                grossMarginPct: $grossMarginPct
            ));
        }

        return $cost;
    }
}
