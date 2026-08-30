<?php

declare(strict_types=1);

namespace App\Modules\X139\Events;

final class ConversionUploaded
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $jobId,
        public readonly int $conversionValueCents
    ) {}
}
