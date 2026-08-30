<?php

declare(strict_types=1);

namespace App\Modules\X125\Events;

final class FlowChanged
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $flowId,
        public readonly string $action
    ) {}
}
