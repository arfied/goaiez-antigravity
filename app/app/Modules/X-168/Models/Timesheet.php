<?php

declare(strict_types=1);

namespace App\Modules\X168\Models;

use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model
{
    protected $table = 'timesheets';

    protected $guarded = [];

    protected $casts = [
        'total_hours' => 'float',
        'period_start' => 'date',
        'period_end' => 'date',
    ];
}
