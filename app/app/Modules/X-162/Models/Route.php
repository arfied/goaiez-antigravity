<?php

declare(strict_types=1);

namespace App\Modules\X162\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $table = 'routes';

    protected $guarded = [];

    protected $casts = [
        'stop_order' => 'array',
        'total_distance_km' => 'float',
    ];
}
