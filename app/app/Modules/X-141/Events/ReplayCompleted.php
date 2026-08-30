<?php

declare(strict_types=1);

namespace App\Modules\X141\Events;

final class ReplayCompleted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $replayRunId,
        public readonly int $eventsReplayed
    ) {}
}
