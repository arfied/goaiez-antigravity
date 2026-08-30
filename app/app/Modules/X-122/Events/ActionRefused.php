<?php

declare(strict_types=1);

namespace App\Modules\X122\Events;

final class ActionRefused
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $actionName,
        public readonly string $refusalCode,
        public readonly string $reason
    ) {}
}
