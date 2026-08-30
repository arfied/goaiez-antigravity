<?php

declare(strict_types=1);

namespace App\Modules\CMail\Events;

final class EmailComplained
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $mailDomainId,
        public readonly float $complaintRate
    ) {}
}
