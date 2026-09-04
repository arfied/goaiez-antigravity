<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierRoster extends Model
{
    protected $table = 'carrier_roster';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_rcs' => 'boolean',
        'supports_voice' => 'boolean',
        'cost_per_msg_cents' => 'integer',
        'cost_per_min_cents' => 'integer',
    ];
}
