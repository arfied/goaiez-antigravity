<?php

declare(strict_types=1);

namespace App\Modules\X149\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class QualitySeries extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'quality_series';

    protected $guarded = [];

    protected $casts = [
        'refusal_rate' => 'float',
        'refusal_rate_drop_pct' => 'float',
        'anomaly_detected' => 'boolean',
        'recorded_at' => 'datetime',
    ];
}
