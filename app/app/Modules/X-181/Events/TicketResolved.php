<?php

declare(strict_types=1);

namespace App\Modules\X181\Events;

final class TicketResolved
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $ticketId,
        public readonly ?int $personId
    ) {}
}
