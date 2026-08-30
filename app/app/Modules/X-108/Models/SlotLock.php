<?php

declare(strict_types=1);

namespace App\Modules\X108\Models;

use Illuminate\Database\Eloquent\Model;

class SlotLock extends Model
{
    protected $table = 'slot_locks';

    protected $guarded = [];

    protected $casts = [
        'slot_start' => 'datetime',
        'slot_end' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
