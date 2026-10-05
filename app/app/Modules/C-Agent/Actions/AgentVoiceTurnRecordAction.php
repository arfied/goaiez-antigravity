<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Actions;

use App\Modules\CAgent\Models\AgentTurn;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * Record what was said on a call the AI receptionist answered, a batch of turns at a time (AI receptionist plan, wave 3a).
 *
 * The voice worker speaks and listens; this application keeps the record. Each turn is the caller's words and the
 * receptionist's reply, keyed (call, turn number), so a batch the worker retries writes nothing twice.
 *
 * The tenant is the one already established by the caller from the call token, never a parameter.
 */
final class AgentVoiceTurnRecordAction
{
    /**
     * @param  list<array{turn: int, caller: string, agent: string, metrics: array<string, int|float>}>  $turns
     * @return int how many of the turns were new
     */
    public function record(int $callId, array $turns): int
    {
        $businessId = Tenancy::idOrFail();
        $now = Carbon::now();
        $written = 0;

        foreach ($turns as $turn) {
            $written += AgentTurn::query()->insertOrIgnore([
                'business_id' => $businessId,
                'call_id' => $callId,
                'turn_number' => $turn['turn'],
                'user_message' => $turn['caller'],
                'agent_reply' => $turn['agent'],
                'status' => 'answered',
                'metrics' => $turn['metrics'] === [] ? null : json_encode($turn['metrics'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $written;
    }
}
