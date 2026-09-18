<?php

declare(strict_types=1);

namespace App\Modules\X181\Ui;

use App\Modules\X181\Actions\QaTicketReopenAction;
use App\Modules\X181\Models\QaTicket;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Resolved tickets'])]
class Resolution extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $tenantId = Tenancy::id() ?: 0;
            if ($tenantId <= 0) {
                abort(403, 'Tenant context is required');
            }
            $this->businessId = (int) $tenantId;
        }
        Tenancy::set($this->businessId);
    }

    public bool $isSample = false;

    /** The panel is the house error surface, reachable only from a domain refusal this module does not yet raise; infrastructure faults propagate by design */
    public ?string $actionNotice = null;

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
    }

    public function reopen(int $ticketId): void
    {
        $this->actionNotice = null;
        app(QaTicketReopenAction::class)->handle($this->businessId, $ticketId);
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        $tickets = collect();

        if ($this->isSample) {
            $tickets = collect([
                (object) [
                    'id' => 9991,
                    'subject' => 'Sample resolved on time',
                    'resolved_at' => now()->subHours(2),
                    'sla_due_at' => now()->subHours(1),
                    'resolution_notes' => 'Fixed immediately',
                    'csat_requested_at' => now()->subHour(),
                ],
                (object) [
                    'id' => 9992,
                    'subject' => 'Sample resolved late',
                    'resolved_at' => now()->subHours(1),
                    'sla_due_at' => now()->subHours(3),
                    'resolution_notes' => 'Fixed later',
                    'csat_requested_at' => null,
                ],
            ]);
        } else {
            $tickets = QaTicket::where('business_id', $this->businessId)
                ->whereIn('status', ['resolved', 'closed'])
                ->orderBy('resolved_at', 'desc')
                ->get();
        }

        return view('x-181::resolution', [
            'tickets' => $tickets,
        ]);
    }
}
