<?php

declare(strict_types=1);

namespace App\Modules\X162\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Route extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'routes';

    protected $guarded = [];

    protected $casts = [
        'stop_order' => 'array',
        'total_distance_km' => 'float',
    ];
}
