<?php

declare(strict_types=1);

namespace App\Modules\X01\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class TakeoverLatch extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'takeover_latches';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'latched_at' => 'datetime',
        'released_at' => 'datetime',
    ];
}
