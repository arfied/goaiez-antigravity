<?php

declare(strict_types=1);

namespace App\Modules\X204\Events;

final class PermitGranted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $permitId,
        public readonly string $recipientPhone,
        public readonly string $channel
    ) {}
}
