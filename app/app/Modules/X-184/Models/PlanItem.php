<?php

declare(strict_types=1);

namespace App\Modules\X184\Models;

use Illuminate\Database\Eloquent\Model;

class PlanItem extends Model
{
    protected $table = 'plan_items';

    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
        'is_scheduled' => 'boolean',
    ];
}
