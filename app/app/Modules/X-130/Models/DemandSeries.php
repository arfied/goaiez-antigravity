<?php

declare(strict_types=1);

namespace App\Modules\X130\Models;

use Illuminate\Database\Eloquent\Model;

class DemandSeries extends Model
{
    protected $table = 'demand_series';

    protected $guarded = [];

    protected $casts = [
        'demand_index' => 'float',
        'source_count' => 'integer',
        'is_published' => 'boolean',
    ];

    public function region()
    {
        return $this->belongsTo(DemandRegion::class, 'region_id');
    }
}
