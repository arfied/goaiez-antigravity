<?php

declare(strict_types=1);

namespace App\Modules\CMail\Events;

final class EmailSent
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $mailDomainId,
        public readonly string $recipientEmail,
        public readonly string $sendType
    ) {}
}
