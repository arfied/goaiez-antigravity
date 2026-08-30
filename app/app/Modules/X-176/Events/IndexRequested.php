<?php

declare(strict_types=1);

namespace App\Modules\X176\Events;

final class IndexRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $url
    ) {}
}
