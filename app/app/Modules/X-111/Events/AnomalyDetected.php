<?php

declare(strict_types=1);

namespace App\Modules\X111\Events;

final class AnomalyDetected
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $anomalyType,
        public readonly array $metadata
    ) {}
}
