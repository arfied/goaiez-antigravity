<?php

declare(strict_types=1);

namespace App\Modules\X181\Ui;

use App\Modules\X181\Actions\QaTicketResolveAction;
use App\Modules\X181\Domain\TicketAlreadyResolvedException;
use App\Modules\X181\Models\QaTicket;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'QA queue'])]
class QaQueueSlaDueAt extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public ?string $actionNotice = null;

    public string $noticeType = 'success';

    public ?int $resolvingTicketId = null;

    public string $resolutionNotes = '';

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

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
        $this->resolvingTicketId = null;
        $this->resolutionNotes = '';
        $this->actionNotice = null;
    }

    public function startResolve(int $id): void
    {
        $this->resolvingTicketId = $id;
        $this->resolutionNotes = '';
    }

    public function cancelResolve(): void
    {
        $this->resolvingTicketId = null;
        $this->resolutionNotes = '';
    }

    public function resolve(int $ticketId, string $notes): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        $action = app(QaTicketResolveAction::class);
        try {
            $action->handle($this->businessId, $ticketId, $notes);
        } catch (TicketAlreadyResolvedException $e) {
            $this->noticeType = 'warning';
            $this->actionNotice = $e->getMessage();
            $this->resolvingTicketId = null;
            $this->resolutionNotes = '';

            return;
        }

        $this->noticeType = 'success';
        $this->actionNotice = '✅ Ticket resolved successfully.';
        $this->resolvingTicketId = null;
        $this->resolutionNotes = '';
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        $tickets = collect();
        if (! $this->isSample) {
            $tickets = QaTicket::where('business_id', $this->businessId)
                ->whereIn('status', ['open', 'in_progress'])
                ->orderBy('sla_due_at', 'asc')
                ->get()
                ->map(function ($t) {
                    $t->is_breached = $t->sla_due_at && $t->sla_due_at <= now();

                    return $t;
                });
        } else {
            $tickets = collect([
                (object) [
                    'id' => 999,
                    'subject' => 'Sample ticket',
                    'sla_due_at' => now()->subHours(1),
                    'is_breached' => true,
                ],
                (object) [
                    'id' => 1000,
                    'subject' => 'Another sample ticket',
                    'sla_due_at' => now()->addHours(2),
                    'is_breached' => false,
                ],
            ]);
        }

        $isEmpty = ! $this->isSample && $tickets->isEmpty();

        return view('x-181::qa-queue-sladueat', [
            'tickets' => $tickets,
            'isEmpty' => $isEmpty,
        ]);
    }
}
