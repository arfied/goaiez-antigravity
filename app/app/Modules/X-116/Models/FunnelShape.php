<?php

declare(strict_types=1);

namespace App\Modules\X116\Models;

use Illuminate\Database\Eloquent\Model;

class FunnelShape extends Model
{
    protected $table = 'funnel_shapes';

    protected $guarded = [];

    protected $casts = [
        'steps_flow' => 'array',
    ];
}
