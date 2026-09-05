<?php

declare(strict_types=1);

namespace App\Modules\X111\Actions;

use App\Modules\X111\Models\TenantTicket;

final class ResolveTicketAction
{
    public function handle(TenantTicket $ticket): void
    {
        $ticket->update(['status' => 'resolved']);
    }
}
