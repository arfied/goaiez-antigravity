<?php

declare(strict_types=1);

namespace App\Modules\X209\Events;

final class FixerEscalated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $commandId,
        public readonly string $reason
    ) {}
}
