<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use Illuminate\Database\Eloquent\Model;

class Funnel extends Model
{
    protected $table = 'funnels';

    protected $guarded = [];

    protected $casts = [
        'steps' => 'array',
        'device_routing' => 'array',
        'click_cap' => 'integer',
        'clicks_count' => 'integer',
        'expires_at' => 'datetime',
    ];
}
