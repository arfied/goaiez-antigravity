<?php

declare(strict_types=1);

namespace App\Modules\X166\Actions;

use App\Modules\X166\Models\JobCost;

final class MarginReportAction
{
    public function handle(int $businessId, string $groupBy = 'job'): array
    {
        $query = JobCost::where('business_id', $businessId);

        return match ($groupBy) {
            'tech' => $query->selectRaw('tech_id, sum(revenue_cents) as total_revenue, sum(total_cost_cents) as total_cost, sum(gross_margin_cents) as total_margin')
                ->groupBy('tech_id')->get()->toArray(),
            'service' => $query->selectRaw('service_type, sum(revenue_cents) as total_revenue, sum(total_cost_cents) as total_cost, sum(gross_margin_cents) as total_margin')
                ->groupBy('service_type')->get()->toArray(),
            'source' => $query->selectRaw('source, sum(revenue_cents) as total_revenue, sum(total_cost_cents) as total_cost, sum(gross_margin_cents) as total_margin')
                ->groupBy('source')->get()->toArray(),
            default => $query->get()->toArray(),
        };
    }
}
