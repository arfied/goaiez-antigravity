<?php

declare(strict_types=1);

namespace App\Modules\X01\Models;

use Illuminate\Database\Eloquent\Model;

class TakeoverLatch extends Model
{
    protected $table = 'takeover_latches';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'latched_at' => 'datetime',
        'released_at' => 'datetime',
    ];
}
