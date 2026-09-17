<?php

declare(strict_types=1);

namespace App\Modules\CAi\Listeners;

use App\Modules\CAgent\Events\AgentTurnStarted;
use App\Modules\CAi\Actions\AiCompleteAction;

final class RecordAiCallOnAgentTurnStarted
{
    public function handle(AgentTurnStarted $event): void
    {
        app(AiCompleteAction::class)->handle(
            businessId: $event->businessId,
            prompt: $event->userMessage,
            taskId: null
        );
    }
}
