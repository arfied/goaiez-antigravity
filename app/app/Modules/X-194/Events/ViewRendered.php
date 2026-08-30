<?php

declare(strict_types=1);

namespace App\Modules\X194\Events;

final class ViewRendered
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $viewId,
        public readonly string $timezone
    ) {}
}
