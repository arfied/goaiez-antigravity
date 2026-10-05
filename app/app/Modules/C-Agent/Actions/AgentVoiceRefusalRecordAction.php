<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Actions;

use App\Modules\CAgent\Models\AgentRefusal;
use App\Support\Tenancy;

/**
 * Record that the AI receptionist refused something on a phone call (AI receptionist plan, wave 3b) — today, a price it had
 * no confirmed figure for. The refusal names the call and keeps what the caller asked.
 *
 * The tenant is the one already established by the caller from the call token, never a parameter.
 */
final class AgentVoiceRefusalRecordAction
{
    public function record(int $callId, string $refusalCode, string $reason, string $callerAsked): void
    {
        $refusal = new AgentRefusal;
        $refusal->forceFill([
            'business_id' => Tenancy::idOrFail(),
            'call_id' => $callId,
            'refusal_code' => $refusalCode,
            'reason' => $reason,
            'user_input' => $callerAsked,
        ])->save();
    }
}
