<?php

declare(strict_types=1);

namespace App\Modules\X181\Actions;

use App\Modules\X181\Domain\TicketAlreadyResolvedException;
use App\Modules\X181\Events\TicketResolved;
use App\Modules\X181\Models\QaTicket;
use Illuminate\Support\Facades\Event;

final class QaTicketResolveAction
{
    public function handle(int $businessId, int $ticketId, string $resolutionNotes): QaTicket
    {
        $ticket = QaTicket::where('business_id', $businessId)->findOrFail($ticketId);

        if ($ticket->status === 'resolved') {
            throw new TicketAlreadyResolvedException("Ticket #{$ticket->id} is already resolved; nothing was saved.");
        }

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => $resolutionNotes,
        ]);

        Event::dispatch(new TicketResolved(
            businessId: $businessId,
            ticketId: $ticket->id,
            personId: $ticket->person_id
        ));

        return $ticket;
    }
}
