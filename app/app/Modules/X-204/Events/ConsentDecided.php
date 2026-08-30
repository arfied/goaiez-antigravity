<?php

declare(strict_types=1);

namespace App\Modules\X204\Events;

final class ConsentDecided
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $recipientPhone,
        public readonly bool $granted,
        public readonly ?string $reason = null
    ) {}
}
