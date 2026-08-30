<?php

declare(strict_types=1);

namespace App\Modules\X206\Events;

final class CredentialRevealed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $credentialId,
        public readonly string $serviceName,
        public readonly ?int $userId = null
    ) {}
}
