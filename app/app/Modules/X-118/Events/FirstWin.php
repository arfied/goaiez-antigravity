<?php

declare(strict_types=1);

namespace App\Modules\X118\Events;

final class FirstWin
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $testCallSid
    ) {}
}
