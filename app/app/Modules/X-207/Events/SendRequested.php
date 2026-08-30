<?php

declare(strict_types=1);

namespace App\Modules\X207\Events;

final class SendRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $deviceTokenId,
        public readonly array $sanitizedPayload
    ) {}
}
