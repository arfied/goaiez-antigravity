<?php

declare(strict_types=1);

namespace App\Modules\X181\Actions;

use App\Modules\X181\Models\QaTicket;

/**
 * (R245) Resolution screen reopen = status open, resolved_at null, notes kept, SLA restarted 24h from reopen; emits nothing (manifest declares ticket.created and ticket.resolved only)
 */
final class QaTicketReopenAction
{
    public function handle(int $businessId, int $ticketId): QaTicket
    {
        $ticket = QaTicket::where('business_id', $businessId)->findOrFail($ticketId);
        $ticket->update([
            'status' => 'open',
            'resolved_at' => null,
            'sla_due_at' => now()->addHours(24),
        ]);

        return $ticket;
    }
}
