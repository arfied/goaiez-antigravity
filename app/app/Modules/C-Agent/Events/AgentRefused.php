<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Events;

final class AgentRefused
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $refusalCode,
        public readonly string $reason,
        public readonly string $userInput
    ) {}
}
