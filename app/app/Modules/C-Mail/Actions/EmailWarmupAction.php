<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Modules\CMail\Exceptions\ConstantWarmupQuantityRefused;
use App\Modules\CMail\Models\WarmupCalendar;
use App\Services\Config\DefaultsRegistry;

final class EmailWarmupAction
{
    public const DAILY_ALLOWANCE = 100;

    /** the published warm-up ladder, before jitter */
    private const BASE_LADDER = [1 => 50, 2 => 100, 3 => 200, 4 => 400, 5 => 800];

    /** G15-31: every quantity is a RANGE plus jitter; a constant is the signature */
    public const JITTER_PCT = 20;

    private function jitterPct(): int
    {
        return $this->registry->int('mail.warmup.jitter_pct');
    }

    private function dailyAllowance(): int
    {
        return $this->registry->int('mail.warmup.daily_allowance');
    }

    public function __construct(private DefaultsRegistry $registry) {}

    public function handle(
        int $businessId,
        int $mailDomainId,
        int $currentDay = 2,
        ?int $dailyAllowance = null,
        ?array $schedule = null
    ): WarmupCalendar {
        $dailyAllowance ??= $this->dailyAllowance();
        if ($schedule === null) {
            $schedule = [];

            foreach (self::BASE_LADDER as $day => $base) {
                $spread = intdiv($base * $this->jitterPct(), 100);
                $min = $base - $spread;
                $max = $base + $spread;

                $schedule["day_{$day}"] = [
                    'min' => $min,
                    'max' => $max,
                    'quantity' => random_int($min, $max),
                ];
            }
        } else {
            foreach ($schedule as $day => $entry) {
                if (! is_array($entry) || ! isset($entry['min'], $entry['max']) || $entry['min'] >= $entry['max']) {
                    throw ConstantWarmupQuantityRefused::forDay((string) $day);
                }
            }
        }

        return WarmupCalendar::updateOrCreate(
            ['business_id' => $businessId, 'mail_domain_id' => $mailDomainId],
            [
                'current_day' => $currentDay,
                'daily_allowance' => $dailyAllowance,
                'sent_today' => 0,
                'is_warmed' => false,
                'schedule' => $schedule,
            ]
        );
    }
}
