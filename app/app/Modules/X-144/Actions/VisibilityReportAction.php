<?php

declare(strict_types=1);

namespace App\Modules\X144\Actions;

use App\Modules\X144\Models\VisibilityAnswer;

final class VisibilityReportAction
{
    public function getReport(int $businessId): array
    {
        $answers = VisibilityAnswer::where('business_id', $businessId)->get();

        $total = $answers->count();
        $mentioned = $answers->where('tenant_mentioned', true)->count();
        $outranked = $answers->where('competitor_outranking', true)->count();

        return [
            'total_queries_tracked' => $total,
            'tenant_mentions_count' => $mentioned,
            'competitor_outranked_count' => $outranked,
            'visibility_rate' => $total > 0 ? round(($mentioned / $total) * 100, 1) : 0.0,
        ];
    }
}
