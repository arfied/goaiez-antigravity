<?php

declare(strict_types=1);

namespace App\Modules\X66\Models;

use Illuminate\Database\Eloquent\Model;

class CallSession extends Model
{
    protected $table = 'call_sessions';

    protected $guarded = [];

    protected $casts = [
        'latency_ms' => 'integer',
        'fallback_triggered' => 'boolean',
    ];
}
