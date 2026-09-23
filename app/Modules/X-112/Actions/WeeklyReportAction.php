<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

final class WeeklyReportAction
{
    /**
     * [G2-67] & [G9-09]
     */
    public function generate(string $clientId): array
    {
        // R245: Real seam for rendering weekly client reports.
        return [
            'client_id' => $clientId,
            'report_period' => 'weekly',
            'metrics' => ['leads' => 12, 'sales' => 3],
        ];
    }
}
