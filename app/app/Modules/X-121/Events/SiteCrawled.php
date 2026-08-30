<?php

declare(strict_types=1);

namespace App\Modules\X121\Events;

final class SiteCrawled
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $siteId,
        public readonly string $domain,
    ) {}
}
