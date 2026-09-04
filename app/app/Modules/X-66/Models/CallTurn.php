<?php

declare(strict_types=1);

namespace App\Modules\X66\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CallTurn extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'call_turns';

    protected $guarded = [];

    protected $casts = [
        'turn_index' => 'integer',
        'barge_in_latency_ms' => 'integer',
    ];
}
