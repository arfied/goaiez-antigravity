<?php

declare(strict_types=1);

namespace App\Modules\X166\Models;

use Illuminate\Database\Eloquent\Model;

class JobCost extends Model
{
    protected $table = 'job_costs';

    protected $guarded = [];

    protected $casts = [
        'labor_cost_cents' => 'integer',
        'materials_cost_cents' => 'integer',
        'overhead_cost_cents' => 'integer',
        'total_cost_cents' => 'integer',
        'revenue_cents' => 'integer',
        'gross_margin_cents' => 'integer',
        'gross_margin_pct' => 'float',
        'is_sample' => 'boolean',
    ];
}
