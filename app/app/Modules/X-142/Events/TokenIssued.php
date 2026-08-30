<?php

declare(strict_types=1);

namespace App\Modules\X142\Events;

final class TokenIssued
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $tokenId,
        public readonly string $tokenName,
        public readonly string $roleScope
    ) {}
}
