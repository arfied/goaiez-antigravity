<?php

declare(strict_types=1);

namespace App\Modules\X16\Models;

use Illuminate\Database\Eloquent\Model;

class GeoGrid extends Model
{
    protected $table = 'geo_grids';

    protected $guarded = [];

    protected $casts = [
        'grid_points' => 'array',
        'center_lat' => 'float',
        'center_lng' => 'float',
        'radius_km' => 'integer',
    ];
}
