<?php

declare(strict_types=1);

namespace App\Modules\X219\Events;

final class RosterChanged
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $targetModule,
        public readonly string $changeType
    ) {}
}
