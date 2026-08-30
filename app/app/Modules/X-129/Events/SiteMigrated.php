<?php

declare(strict_types=1);

namespace App\Modules\X129\Events;

final class SiteMigrated
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $domain,
        public readonly int $redirectsCount
    ) {}
}
