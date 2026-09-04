<?php

declare(strict_types=1);

namespace App\Modules\X184\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class PlanItem extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'plan_items';

    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
        'is_scheduled' => 'boolean',
    ];
}
