<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierHealth extends Model
{
    protected $table = 'carrier_health';

    protected $guarded = [];

    protected $casts = [
        'latency_ms' => 'integer',
        'error_rate_pct' => 'integer',
    ];
}
