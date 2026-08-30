<?php

declare(strict_types=1);

namespace App\Modules\X130\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherOverlay extends Model
{
    protected $table = 'weather_overlays';

    protected $guarded = [];

    protected $casts = [
        'severity_index' => 'float',
        'observed_at' => 'datetime',
    ];
}
