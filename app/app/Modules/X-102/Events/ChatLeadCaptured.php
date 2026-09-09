<?php

declare(strict_types=1);

namespace App\Modules\X102\Events;

final class ChatLeadCaptured
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $leadId,
        public readonly int $personId,
        public readonly string $name,
        public readonly string $phone,
        public readonly ?string $message = null
    ) {}
}
