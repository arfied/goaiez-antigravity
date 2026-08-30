<?php

declare(strict_types=1);

namespace App\Modules\X206\Events;

final class CredentialFailed
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $serviceName,
        public readonly string $reason
    ) {}
}
