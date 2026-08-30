<?php

declare(strict_types=1);

namespace App\Modules\X129\Events;

final class DomainVerified
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $domain
    ) {}
}
