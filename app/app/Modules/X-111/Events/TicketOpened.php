<?php

declare(strict_types=1);

namespace App\Modules\X111\Events;

final class TicketOpened
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $ticketId,
        public readonly string $source
    ) {}
}
