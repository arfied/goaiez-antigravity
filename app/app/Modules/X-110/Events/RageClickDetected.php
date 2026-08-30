<?php

declare(strict_types=1);

namespace App\Modules\X110\Events;

final class RageClickDetected
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $elementSelector,
        public readonly int $clicksCount
    ) {}
}
