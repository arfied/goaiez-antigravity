<?php

declare(strict_types=1);

namespace App\Modules\X155\Events;

final class FormSpamRejected
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $formDefinitionId,
        public readonly string $reason,
        public readonly ?string $ipAddress = null
    ) {}
}
