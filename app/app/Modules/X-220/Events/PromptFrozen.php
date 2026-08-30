<?php

declare(strict_types=1);

namespace App\Modules\X220\Events;

final class PromptFrozen
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $promptId,
        public readonly string $promptKey,
        public readonly int $version
    ) {}
}
