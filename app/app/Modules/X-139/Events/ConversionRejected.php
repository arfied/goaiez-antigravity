<?php

declare(strict_types=1);

namespace App\Modules\X139\Events;

final class ConversionRejected
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $jobId,
        public readonly string $reason
    ) {}
}
