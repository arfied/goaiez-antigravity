<?php

declare(strict_types=1);

namespace App\Modules\X128\Events;

final class OrphanDetected
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $eventName,
        public readonly string $sourceModule,
        public readonly string $reason
    ) {}
}
