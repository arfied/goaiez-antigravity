<?php

declare(strict_types=1);

namespace App\Modules\X200\Models;

use Illuminate\Database\Eloquent\Model;

class CallSchedule extends Model
{
    protected $table = 'call_schedules';

    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'is_completed' => 'boolean',
    ];
}
