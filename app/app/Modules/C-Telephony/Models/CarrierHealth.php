<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $carrier_name
 * @property string $status
 * @property int $latency_ms
 * @property int $error_rate_pct
 */
class CarrierHealth extends Model
{
    protected $table = 'carrier_health';

    protected $guarded = [];

    protected $casts = [
        'latency_ms' => 'integer',
        'error_rate_pct' => 'integer',
    ];
}
