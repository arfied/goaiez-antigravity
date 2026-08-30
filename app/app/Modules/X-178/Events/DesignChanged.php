<?php

declare(strict_types=1);

namespace App\Modules\X178\Events;

final class DesignChanged
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $changeId,
        public readonly string $blockRef,
        public readonly float $contrastRatio
    ) {}
}
