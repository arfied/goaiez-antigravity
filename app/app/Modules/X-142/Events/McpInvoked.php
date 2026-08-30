<?php

declare(strict_types=1);

namespace App\Modules\X142\Events;

final class McpInvoked
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $actionName,
        public readonly bool $success
    ) {}
}
