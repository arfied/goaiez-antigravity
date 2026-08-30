<?php

declare(strict_types=1);

namespace App\Modules\X196\Events;

final class ProspectInjected
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $injectionId,
        public readonly string $attestationId
    ) {}
}
