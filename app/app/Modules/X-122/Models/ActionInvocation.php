<?php

declare(strict_types=1);

namespace App\Modules\X122\Models;

use Illuminate\Database\Eloquent\Model;

class ActionInvocation extends Model
{
    protected $table = 'action_invocations';

    protected $guarded = [];

    protected $casts = [
        'parameters' => 'array',
        'result' => 'array',
    ];
}
