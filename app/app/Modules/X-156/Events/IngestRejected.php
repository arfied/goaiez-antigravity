<?php

declare(strict_types=1);

namespace App\Modules\X156\Events;

final class IngestRejected
{
    public function __construct(
        public readonly int $businessId,
        public readonly ?int $sourceId,
        public readonly string $reason
    ) {}
}
