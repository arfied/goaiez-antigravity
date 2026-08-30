<?php

declare(strict_types=1);

namespace App\Modules\X127\Events;

final class TenantzeroMetricPublished
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $metricKey,
        public readonly string $publishedValue
    ) {}
}
