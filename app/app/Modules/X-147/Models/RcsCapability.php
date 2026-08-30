<?php

declare(strict_types=1);

namespace App\Modules\X147\Models;

use Illuminate\Database\Eloquent\Model;

class RcsCapability extends Model
{
    protected $table = 'rcs_capabilities';

    protected $guarded = [];

    protected $casts = [
        'has_rcs' => 'boolean',
        'degraded_count' => 'integer',
        'last_checked_at' => 'datetime',
    ];
}
