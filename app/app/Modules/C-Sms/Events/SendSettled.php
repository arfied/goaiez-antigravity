<?php

declare(strict_types=1);

namespace App\Modules\CSms\Events;

final class SendSettled
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $compositionId,
        public readonly ?string $source,
        public readonly string $status,   // 'sent' | 'refused'
        public readonly ?string $reason,  // the refusal reason, null when sent
    ) {}
}
