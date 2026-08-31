<?php

declare(strict_types=1);

namespace App\Modules\X181\Ui;

use App\Modules\X181\Models\QaTicket;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QaQueueSlaDueAt extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $tickets = ($this->businessId > 0)
            ? QaTicket::where('business_id', $this->businessId)->where('status', 'open')->orderBy('sla_due_at', 'asc')->get()
            : collect();

        return view('x-181::qa-queue-sladueat', [
            'tickets' => $tickets,
        ]);
    }
}
