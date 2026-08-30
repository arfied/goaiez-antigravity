<?php

declare(strict_types=1);

namespace App\Modules\CMail\Models;

use Illuminate\Database\Eloquent\Model;

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
