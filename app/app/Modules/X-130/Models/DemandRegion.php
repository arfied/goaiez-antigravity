<?php

declare(strict_types=1);

namespace App\Modules\X130\Models;

use Illuminate\Database\Eloquent\Model;

class DemandRegion extends Model
{
    protected $table = 'demand_regions';

    protected $guarded = [];

    public function series()
    {
        return $this->hasMany(DemandSeries::class, 'region_id');
    }

    public function weatherOverlays()
    {
        return $this->hasMany(WeatherOverlay::class, 'region_id');
    }
}
