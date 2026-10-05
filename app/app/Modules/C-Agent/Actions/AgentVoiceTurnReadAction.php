<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Actions;

use App\Modules\CAgent\Models\AgentTurn;
use App\Support\Tenancy;

/**
 * What was said on calls the AI receptionist answered, for the owner's calls page (AI receptionist plan, 2026-10-05).
 *
 * The reader beside {@see AgentVoiceTurnRecordAction}, so a screen outside C-Agent never reaches into its table. Turns
 * come back grouped by call, in the order they were spoken. The tenant is the one already established by the caller.
 */
final class AgentVoiceTurnReadAction
{
    /**
     * @param  list<int>  $callIds
     * @return array<int, list<array{turn: int, caller: string, agent: string}>>
     */
    public function forCalls(array $callIds): array
    {
        Tenancy::idOrFail();

        if ($callIds === []) {
            return [];
        }

        $byCall = [];

        $turns = AgentTurn::query()
            ->whereIn('call_id', $callIds)
            ->orderBy('call_id')
            ->orderBy('turn_number')
            ->get(['call_id', 'turn_number', 'user_message', 'agent_reply']);

        foreach ($turns as $turn) {
            $byCall[(int) $turn->call_id][] = [
                'turn' => (int) $turn->turn_number,
                'caller' => (string) $turn->user_message,
                'agent' => (string) $turn->agent_reply,
            ];
        }

        return $byCall;
    }
}
