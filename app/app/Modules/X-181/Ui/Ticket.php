<?php

declare(strict_types=1);

namespace App\Modules\X181\Ui;

use App\Modules\CReviews\Actions\ReviewRequestReadAction;
use App\Modules\X181\Actions\QaTicketResolveAction;
use App\Modules\X181\Domain\TicketAlreadyResolvedException;
use App\Modules\X181\Models\QaTicket;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Ticket'])]
class Ticket extends Component
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

    #[Locked]
    public int $ticketId = 0;

    public bool $isSample = false;

    public string $resolutionNotes = '';

    /** The panel is the house error surface. Its one domain refusal is TicketAlreadyResolvedException, a stale page resolving a ticket that is already resolved; infrastructure faults propagate by design */
    public ?string $actionNotice = null;

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
    }

    public function resolve(string $notes): void
    {
        $this->actionNotice = null;
        try {
            app(QaTicketResolveAction::class)->handle($this->businessId, $this->ticketId, $notes);
        } catch (TicketAlreadyResolvedException $e) {
            $this->actionNotice = $e->getMessage();
        }
        $this->resolutionNotes = '';
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        $ticket = null;
        $review = null;

        if ($this->isSample) {
            $ticket = (object) [
                'id' => 9999,
                'business_id' => $this->businessId,
                'person_id' => 123,
                'review_request_id' => 456,
                'subject' => 'Sample poor rating',
                'description' => 'Customer was very unhappy with the wait time.',
                'status' => 'open',
                'arrived_at' => now()->subHours(24),
                'sla_due_at' => now()->subHours(2),
                'resolved_at' => null,
                'resolution_notes' => null,
            ];
            $review = (object) [
                'rating' => 2,
            ];
        } elseif ($this->ticketId > 0) {
            $ticket = QaTicket::where('business_id', $this->businessId)
                ->where('id', $this->ticketId)
                ->first();

            if ($ticket && $ticket->review_request_id) {
                $review = app(ReviewRequestReadAction::class)->handle($this->businessId, (int) $ticket->review_request_id);
            }
        }

        return view('x-181::ticket', [
            'ticket' => $ticket,
            'review' => $review,
        ]);
    }
}
