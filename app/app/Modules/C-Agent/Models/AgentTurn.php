<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTurn extends Model
{
    protected $table = 'agent_turns';

    protected $guarded = [];

    protected $casts = [
        'turn_number' => 'integer',
    ];
}
