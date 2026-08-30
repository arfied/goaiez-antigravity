<?php

declare(strict_types=1);

namespace App\Modules\X122\Events;

final class ActionInvoked
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $invocationId,
        public readonly string $actionName,
        public readonly array $parameters,
        public readonly array $result
    ) {}
}
