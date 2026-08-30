<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Events;

final class AgentTurnAnswer
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $turnId,
        public readonly string $userMessage,
        public readonly string $agentReply,
        public readonly string $status
    ) {}
}
