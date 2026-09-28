<?php

declare(strict_types=1);

namespace App\Modules\CSms\Events;

final class SendRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $compositionId,
        public readonly string $recipientPhone,
        public readonly string $messageClass,
        public readonly string $body,
        public readonly int $segmentsCount,
        public readonly ?string $source = null,
    ) {}
}
