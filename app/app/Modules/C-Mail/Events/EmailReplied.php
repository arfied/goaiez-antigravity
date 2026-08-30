<?php

declare(strict_types=1);

namespace App\Modules\CMail\Events;

final class EmailReplied
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $mailDomainId,
        public readonly string $fromEmail,
        public readonly string $subject
    ) {}
}
