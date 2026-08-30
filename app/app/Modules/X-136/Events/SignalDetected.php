<?php

declare(strict_types=1);

namespace App\Modules\X136\Events;

final class SignalDetected
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $signalId,
        public readonly string $prospectIdentifier,
        public readonly string $signalType
    ) {}
}
