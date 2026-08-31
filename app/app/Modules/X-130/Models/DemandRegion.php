<?php

declare(strict_types=1);

namespace App\Modules\X130\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandRegion extends Model
{
    protected $table = 'demand_regions';

    protected $guarded = [];

    /**
     * @return HasMany<DemandSeries, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(DemandSeries::class, 'region_id');
    }

    /**
     * @return HasMany<WeatherOverlay, $this>
     */
    public function weatherOverlays(): HasMany
    {
        return $this->hasMany(WeatherOverlay::class, 'region_id');
    }
}
