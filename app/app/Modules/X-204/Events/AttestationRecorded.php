<?php

declare(strict_types=1);

namespace App\Modules\X204\Events;

final class AttestationRecorded
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $attestationId,
        public readonly string $attestationHash,
        public readonly int $contactsCount
    ) {}
}
