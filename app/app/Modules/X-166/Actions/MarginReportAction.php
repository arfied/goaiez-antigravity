<?php

declare(strict_types=1);

namespace App\Modules\X166\Actions;

use App\Modules\X166\Models\JobCost;

final class MarginReportAction
{
    public function handle(int $businessId, string $groupBy = 'job'): array
    {
        $query = JobCost::where('business_id', $businessId)->where('is_sample', false);

        if ($groupBy === 'tech') {
            return $query->selectRaw('tech_id, sum(revenue_cents) as total_revenue, sum(total_cost_cents) as total_cost, sum(gross_margin_cents) as total_margin')
                ->groupBy('tech_id')->get()->toArray();
        }

        if ($groupBy === 'service') {
            return $query->selectRaw('service_type, sum(revenue_cents) as total_revenue, sum(total_cost_cents) as total_cost, sum(gross_margin_cents) as total_margin')
                ->groupBy('service_type')->get()->toArray();
        }

        if ($groupBy === 'source') {
            return $query->selectRaw('source, sum(revenue_cents) as total_revenue, sum(total_cost_cents) as total_cost, sum(gross_margin_cents) as total_margin')
                ->groupBy('source')->get()->toArray();
        }

        return $query->get()->toArray();
    }
}
