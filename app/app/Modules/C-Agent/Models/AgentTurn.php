<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property int $session_id
 * @property int $turn_number
 * @property string $user_message
 * @property string $agent_reply
 * @property ?string $intent
 * @property ?array<string, mixed> $context
 * @property ?int $call_id
 * @property ?array<string, int|float> $metrics
 */
class AgentTurn extends Model
{
    protected $table = 'agent_turns';

    protected $guarded = [];

    protected $casts = [
        'turn_number' => 'integer',
        'metrics' => 'array',
    ];
}
