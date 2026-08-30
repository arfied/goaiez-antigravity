<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Modules\CMail\Models\WarmupCalendar;

final class EmailWarmupAction
{
    public function handle(
        int $businessId,
        int $mailDomainId,
        int $currentDay = 2,
        int $dailyAllowance = 100
    ): WarmupCalendar {
        return WarmupCalendar::updateOrCreate(
            ['business_id' => $businessId, 'mail_domain_id' => $mailDomainId],
            [
                'current_day' => $currentDay,
                'daily_allowance' => $dailyAllowance,
                'sent_today' => 0,
                'is_warmed' => false,
                'schedule' => [
                    'day_1' => 50,
                    'day_2' => 100,
                    'day_3' => 200,
                    'day_4' => 400,
                    'day_5' => 800,
                ],
            ]
        );
    }
}
