<?php

declare(strict_types=1);

namespace App\Modules\X175\Events;

final class AssistantSuggested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $suggestionId,
        public readonly string $responseText
    ) {}
}
