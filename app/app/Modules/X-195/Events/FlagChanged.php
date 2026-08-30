<?php

declare(strict_types=1);

namespace App\Modules\X195\Events;

final class FlagChanged
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $flagKey,
        public readonly bool $isEnabled,
        public readonly int $blastRadiusPct
    ) {}
}
