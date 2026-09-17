<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Events;

/**
 * Emitted once per turn before any branch decides how the turn ends; C-Ai and X-148 consume it.
 */
final class AgentTurnStarted
{
    public function __construct(
        public readonly int $businessId,
        public readonly ?int $conversationId,
        public readonly int $turnNumber,
        public readonly string $userMessage
    ) {}
}
