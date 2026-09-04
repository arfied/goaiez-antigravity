<?php

declare(strict_types=1);

namespace App\Modules\X168\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'timesheets';

    protected $guarded = [];

    protected $casts = [
        'total_hours' => 'float',
        'period_start' => 'date',
        'period_end' => 'date',
    ];
}
