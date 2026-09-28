<?php

declare(strict_types=1);

namespace App\Modules\X181\Actions;

use App\Modules\X181\Events\TicketCreated;
use App\Modules\X181\Models\QaTicket;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\Event;

final class QaTicketCreateAction
{
    public const SLA_HOURS = 24;

    public function __construct(
        private readonly DefaultsRegistry $registry
    ) {}

    private function slaHours(): int
    {
        return $this->registry->int('qa.ticket.sla_hours');
    }

    /**
     * Create QA ticket with SLA starting at ARRIVAL (TEST ANCHOR & §190).
     */
    public function handle(
        int $businessId,
        ?int $personId,
        string $subject,
        ?string $description = null,
        ?int $reviewRequestId = null,
        ?int $slaHours = null
    ): QaTicket {
        $slaHours ??= $this->slaHours();
        $arrivedAt = now();
        $slaDueAt = $arrivedAt->copy()->addHours($slaHours);

        $ticket = QaTicket::create([
            'business_id' => $businessId,
            'person_id' => $personId,
            'review_request_id' => $reviewRequestId,
            'subject' => $subject,
            'description' => $description,
            'status' => 'open',
            'arrived_at' => $arrivedAt,
            'sla_due_at' => $slaDueAt,
        ]);

        Event::dispatch(new TicketCreated(
            businessId: $businessId,
            ticketId: $ticket->id,
            personId: $personId,
            slaDueAt: $slaDueAt->toIso8601String()
        ));

        return $ticket;
    }
}
