<?php

declare(strict_types=1);

namespace App\Modules\X168\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class TimesheetEntry extends Model implements TenantScoped
{
    use BelongsToTenant;

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
