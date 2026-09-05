<?php

declare(strict_types=1);

namespace App\Modules\X66\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CallTurn extends Model
{
    use HasFactory;
    protected $table = 'call_turns';

    protected $guarded = [];

    protected $casts = [
        'turn_index' => 'integer',
        'barge_in_latency_ms' => 'integer',
    ];
}
