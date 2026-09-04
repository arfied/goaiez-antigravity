<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AgentInstruction extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'agent_instructions';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
