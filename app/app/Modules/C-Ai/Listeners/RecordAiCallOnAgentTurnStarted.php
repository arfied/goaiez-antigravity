<?php

declare(strict_types=1);

namespace App\Modules\CAi\Listeners;

use App\Modules\CAgent\Events\AgentTurnStarted;

final class RecordAiCallOnAgentTurnStarted
{
    public function handle(AgentTurnStarted $event): void
    {
        /**
         * C-Ai consumes `agent.turn.started` by contract (plan §264E); no turn uses a model yet (C-Agent answers from facts and templates, and a fact-gated turn must record no AI call — `CAgentTest` states it); when a turn does use a model, the call is recorded HERE, through `AiCompleteAction`, and nowhere else.
         */
    }
}
