<?php

declare(strict_types=1);

namespace App\Modules\CAi\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AiCall extends Model implements TenantScoped
{
    use BelongsToTenant;

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
}
