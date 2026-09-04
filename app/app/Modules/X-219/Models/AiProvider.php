<?php

declare(strict_types=1);

namespace App\Modules\X219\Models;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    protected $table = 'ai_providers';

    protected $guarded = [];

    protected $casts = [
        'latency_p95_ms' => 'integer',
        'error_rate_pct' => 'integer',
    ];
}
