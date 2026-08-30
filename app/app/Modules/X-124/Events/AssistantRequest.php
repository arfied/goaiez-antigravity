<?php

declare(strict_types=1);

namespace App\Modules\X124\Events;

final class AssistantRequest
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $sessionToken,
        public readonly string $actionKey
    ) {}
}
