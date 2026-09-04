<?php

declare(strict_types=1);

namespace App\Modules\X122\Models;

use Illuminate\Database\Eloquent\Model;

class ActionReversal extends Model
{
    protected $table = 'action_reversals';

    protected $guarded = [];

    protected $casts = [
        'parameters' => 'array',
        'reversed_at' => 'datetime',
    ];
}
