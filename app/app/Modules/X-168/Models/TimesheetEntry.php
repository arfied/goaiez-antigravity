<?php

declare(strict_types=1);

namespace App\Modules\X168\Models;

use Illuminate\Database\Eloquent\Model;

class TimesheetEntry extends Model
{
    protected $table = 'timesheet_entries';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'location_lat' => 'float',
        'location_lng' => 'float',
    ];
}
