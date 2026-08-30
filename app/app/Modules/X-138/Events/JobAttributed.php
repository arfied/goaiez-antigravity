<?php

declare(strict_types=1);

namespace App\Modules\X138\Events;

final class JobAttributed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $jobId,
        public readonly string $touchSource
    ) {}
}
