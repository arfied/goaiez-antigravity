<?php

declare(strict_types=1);

namespace App\Modules\X151\Events;

final class FetchRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $url,
        public readonly string $domain
    ) {}
}
