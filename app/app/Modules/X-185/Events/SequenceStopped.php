<?php

declare(strict_types=1);

namespace App\Modules\X185\Events;

final class SequenceStopped
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $sequenceId,
        public readonly string $reason
    ) {}
}
