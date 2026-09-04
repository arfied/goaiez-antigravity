<?php

declare(strict_types=1);

namespace App\Modules\X157\Models;

use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    protected $table = 'deployments';

    protected $guarded = [];

    protected $casts = [
        'speed_index' => 'integer',
        'speed_budget_ms' => 'integer',
        'measured_ttfb_ms' => 'integer',
        'deployed_at' => 'datetime',
    ];
}
