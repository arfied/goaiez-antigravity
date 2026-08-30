<?php

declare(strict_types=1);

namespace App\Modules\X142\Events;

final class TokenRevoked
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $tokenId
    ) {}
}
