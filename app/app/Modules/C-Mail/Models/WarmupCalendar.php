<?php

declare(strict_types=1);

namespace App\Modules\CMail\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property int $current_day
 * @property int $daily_allowance
 * @property int $sent_today
 * @property bool $is_warmed
 * @property ?array<string, mixed> $schedule
 */
class WarmupCalendar extends Model
{
    protected $table = 'warmup_calendars';

    protected $guarded = [];

    protected $casts = [
        'current_day' => 'integer',
        'daily_allowance' => 'integer',
        'sent_today' => 'integer',
        'is_warmed' => 'boolean',
        'schedule' => 'array',
    ];
}
