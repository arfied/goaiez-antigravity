<?php

declare(strict_types=1);

namespace App\Modules\CAi\Models;

use Database\Factories\AiCallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiCall extends Model
{
    use HasFactory;

    protected $table = 'ai_calls';

    protected $guarded = [];

    protected $casts = [
        'cost_cents' => 'integer',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'usage_unavailable' => 'boolean',
        'ttft_ms' => 'integer',
        'latency_ms' => 'integer',
    ];

    protected static function newFactory()
    {
        return AiCallFactory::new();
    }
}
