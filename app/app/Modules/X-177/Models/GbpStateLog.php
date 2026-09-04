<?php

declare(strict_types=1);

namespace App\Modules\X177\Models;

use Illuminate\Database\Eloquent\Model;

class GbpStateLog extends Model
{
    protected $table = 'gbp_state_log';

    protected $guarded = [];

    protected $casts = [
        'details' => 'array',
    ];
}
