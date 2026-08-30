<?php

declare(strict_types=1);

namespace App\Modules\X171\Events;

final class PhotoCaptured
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $jobId,
        public readonly string $photoUrl
    ) {}
}
