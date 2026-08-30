<?php

declare(strict_types=1);

namespace App\Modules\X206\Events;

final class CredentialExpiring
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $credentialId,
        public readonly string $serviceName,
        public readonly string $expiresAt
    ) {}
}
