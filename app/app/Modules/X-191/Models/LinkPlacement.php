<?php

declare(strict_types=1);

namespace App\Modules\X191\Models;

use Illuminate\Database\Eloquent\Model;

class LinkPlacement extends Model
{
    protected $table = 'link_placements';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_monitored_at' => 'datetime',
    ];
}
