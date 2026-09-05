<?php

declare(strict_types=1);

namespace App\Modules\X122\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\ActionInvocationFactory;

class ActionInvocation extends Model
{
    use HasFactory;

    protected $table = 'action_invocations';

    protected $guarded = [];

    protected $casts = [
        'parameters' => 'array',
        'result' => 'array',
    ];

    protected static function newFactory()
    {
        return ActionInvocationFactory::new();
    }
}
