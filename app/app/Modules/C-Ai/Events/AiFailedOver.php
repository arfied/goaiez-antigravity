<?php

declare(strict_types=1);

namespace App\Modules\CAi\Events;

final class AiFailedOver
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $primaryModel,
        public readonly string $backupModel,
        public readonly string $reason
    ) {}
}
