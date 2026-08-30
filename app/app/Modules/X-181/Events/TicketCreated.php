<?php

declare(strict_types=1);

namespace App\Modules\X181\Events;

final class TicketCreated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $ticketId,
        public readonly ?int $personId,
        public readonly string $slaDueAt
    ) {}
}
