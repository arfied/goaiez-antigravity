<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\SupportChannel;
use App\Enums\SupportTicketStatus;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

/**
 * One support thread, read once and handed over whole.
 *
 * ⚠️ **A SNAPSHOT RATHER THAN THE MODELS, AND ON THE STAFF PATH THAT IS
 * LOAD-BEARING.** `SupportDesk` reads a thread inside
 * {@see Tenancy::actingAs()} and the console renders it afterwards,
 * with no tenant established — so a lazily-loaded relation on a returned model
 * would run its query outside the tenancy and come back **empty**, which reads
 * as "this ticket has no messages" rather than as a boundary doing its job.
 * Handing over plain values makes that impossible instead of merely unlikely.
 *
 * `AccountSnapshot` is the same shape for the same reason.
 */
final readonly class SupportThread
{
    /**
     * @param  list<SupportThreadMessage>  $messages
     */
    public function __construct(
        public int $ticketId,
        public int $businessId,
        public string $subject,
        public SupportTicketStatus $status,
        public SupportChannel $channel,
        public CarbonImmutable $openedAt,
        public CarbonImmutable $lastMessageAt,
        public array $messages,
    ) {}
}
