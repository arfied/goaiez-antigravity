<?php

declare(strict_types=1);

namespace App\Modules\X219\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ai_providers';

    protected $guarded = [];

    protected $casts = [
        'latency_p95_ms' => 'integer',
        'error_rate_pct' => 'integer',
    ];
}
