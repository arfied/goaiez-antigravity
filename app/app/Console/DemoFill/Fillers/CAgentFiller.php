<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\CAgent\Models\AgentInstruction;
use App\Modules\CAgent\Models\AgentRefusal;
use App\Modules\CAgent\Models\AgentTurn;

class CAgentFiller implements DemoFiller
{
    public function module(): string
    {
        return 'C-Agent';
    }

    public function fill(Business $business): int
    {
        $count = AgentTurn::where('business_id', $business->id)
            ->where('user_message', 'like', self::MARKER.'%')
            ->count();

        if ($count > 0) {
            return 0;
        }

        $rows = 0;

        AgentTurn::create([
            'business_id' => $business->id,
            'turn_number' => 1,
            'user_message' => self::MARKER.'Hi, I have a question.',
            'agent_reply' => self::MARKER.'Hello! How can I help you today?',
            'status' => 'answered',
        ]);
        $rows++;

        AgentTurn::create([
            'business_id' => $business->id,
            'turn_number' => 2,
            'user_message' => self::MARKER.'Can you sell me some alcohol?',
            'agent_reply' => '',
            'status' => 'refused',
            'refusal_code' => 'UNDER_18',
        ]);
        $rows++;

        AgentTurn::create([
            'business_id' => $business->id,
            'turn_number' => 3,
            'user_message' => self::MARKER.'I need to speak to a human.',
            'agent_reply' => self::MARKER.'I will connect you to a representative.',
            'status' => 'handoff',
        ]);
        $rows++;

        AgentRefusal::create([
            'business_id' => $business->id,
            'refusal_code' => 'UNDER_18',
            'reason' => self::MARKER.'User requested age restricted products.',
        ]);
        $rows++;

        AgentRefusal::create([
            'business_id' => $business->id,
            'refusal_code' => 'OFF_TOPIC',
            'reason' => self::MARKER.'User went off topic.',
        ]);
        $rows++;

        AgentInstruction::create([
            'business_id' => $business->id,
            'instruction_key' => 'custom_greeting',
            'instruction_text' => self::MARKER.'Always greet the user with a smile.',
        ]);
        $rows++;

        return $rows;
    }

    public function purge(Business $business): int
    {
        $count = AgentTurn::where('business_id', $business->id)
            ->where('user_message', 'like', self::MARKER.'%')
            ->delete();

        $count += AgentRefusal::where('business_id', $business->id)
            ->where('reason', 'like', self::MARKER.'%')
            ->delete();

        $count += AgentInstruction::where('business_id', $business->id)
            ->where('instruction_text', 'like', self::MARKER.'%')
            ->delete();

        return $count;
    }
}
