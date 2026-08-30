<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Models;

use Illuminate\Database\Eloquent\Model;

class AgentInstruction extends Model
{
    protected $table = 'agent_instructions';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
