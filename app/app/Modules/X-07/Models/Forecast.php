<?php

declare(strict_types=1);

namespace App\Modules\X07\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Forecast extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'forecasts';

    protected $guarded = [];

    protected $casts = [
        'booked_cents' => 'integer',
        'collected_cents' => 'integer',
        'churn_risk_pct' => 'integer',
        'is_high_risk' => 'boolean',
        'alert_created' => 'boolean',
    ];
}
