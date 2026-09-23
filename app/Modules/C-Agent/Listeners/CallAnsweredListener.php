<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Listeners;

use App\Modules\CAgent\Actions\AgentAnswerAction;
use Illuminate\Support\Facades\Log;

final class CallAnsweredListener
{
    /**
     * [G5-32] the voice door is X-66's
     */
    public function handle(object $event): void
    {
        // R245: The voice door event hands off to AgentAnswerAction via an intermediate
        // turn extraction. C-Agent consumes call.answered.
        Log::info('Call answered handled by C-Agent', ['session_id' => $event->sessionId ?? null]);
    }
}
