<?php

declare(strict_types=1);

namespace App\Modules\X122\Events;

final class ActionReversed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $reversalId,
        public readonly int $invocationId,
        public readonly string $actionName
    ) {}
}
