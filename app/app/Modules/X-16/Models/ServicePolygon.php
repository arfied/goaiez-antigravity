<?php

declare(strict_types=1);

namespace App\Modules\X16\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePolygon extends Model
{
    protected $table = 'service_polygons';

    protected $guarded = [];

    protected $casts = [
        'coordinates' => 'array',
        'is_active' => 'boolean',
    ];
}
