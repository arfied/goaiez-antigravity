<?php

declare(strict_types=1);

namespace App\Modules\X178\Events;

final class BlockAdded
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $pageId,
        public readonly string $blockRef
    ) {}
}
