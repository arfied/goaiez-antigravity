<?php

declare(strict_types=1);

namespace App\Modules\X108\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class SlotLock extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'slot_locks';

    protected $guarded = [];

    protected $casts = [
        'slot_start' => 'datetime',
        'slot_end' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
