<?php

declare(strict_types=1);

namespace App\Modules\X197\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceRoute extends Model
{
    protected $table = 'voice_routes';

    protected $guarded = [];

    protected $casts = [
        'cost_per_minute' => 'float',
        'latency_ms' => 'integer',
        'fallback_used' => 'boolean',
    ];
}
