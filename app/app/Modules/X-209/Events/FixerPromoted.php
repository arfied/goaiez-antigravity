<?php

declare(strict_types=1);

namespace App\Modules\X209\Events;

final class FixerPromoted
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $actionName,
        public readonly int $newLevel
    ) {}
}
