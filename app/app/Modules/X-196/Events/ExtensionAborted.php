<?php

declare(strict_types=1);

namespace App\Modules\X196\Events;

final class ExtensionAborted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $sessionId,
        public readonly string $reason
    ) {}
}
