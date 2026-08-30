<?php

declare(strict_types=1);

namespace App\Modules\X10\Models;

use Illuminate\Database\Eloquent\Model;

class Territory extends Model
{
    protected $table = 'territories';

    protected $guarded = [];

    protected $casts = [
        'polygon_geojson' => 'array',
        'zip_codes' => 'array',
    ];
}
