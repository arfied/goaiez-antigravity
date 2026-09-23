<?php

declare(strict_types=1);

namespace App\Modules\X111\Ui;

use App\Modules\X111\Actions\ResolveAlertAction;
use App\Modules\X111\Actions\ResolveTicketAction;
use App\Modules\X111\Domain\OpsEngine;
use App\Modules\X111\Models\OperatorAlert;
use App\Modules\X111\Models\TenantTicket;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Console extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?string $errorMessage = null;

    public string $fullTranscript = '';

    public string $category = 'human_escalation';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::idOrFail();
    }

    public function createTicket(OpsEngine $engine): void
    {
        if (empty($this->fullTranscript) || empty($this->category)) {
            $this->error = 'Transcript and category are required.';

            return;
        }

        $this->error = null;
        $ticket = $engine->createHumanTicket(Tenancy::idOrFail(), $this->fullTranscript, $this->category);

        $this->success = 'Ticket created. This feeds the Escalated Tickets list and dispatches TicketOpened; nothing downstream acts on it yet.';
        $this->fullTranscript = '';
    }

    public function resolveAlert(int $id, ResolveAlertAction $action): void
    {
        $alert = OperatorAlert::where('business_id', $this->businessId)->where('id', $id)->firstOrFail();
        $action->handle($alert);
    }

    public function resolveTicket(int $id, ResolveTicketAction $action): void
    {
        $ticket = TenantTicket::where('business_id', $this->businessId)->where('id', $id)->firstOrFail();
        $action->handle($ticket);
    }

    public function render()
    {
        try {
            $alerts = OperatorAlert::where('business_id', $this->businessId)
                ->where('status', 'open')
                ->orderBy('id', 'desc')
                ->get();

            $tickets = TenantTicket::where('business_id', $this->businessId)
                ->where('status', 'open')
                ->orderBy('id', 'desc')
                ->get();
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load console data';
            $alerts = collect();
            $tickets = collect();
        }

        return view('x-111::console', [
            'alerts' => $alerts,
            'tickets' => $tickets,
        ]);
    }
}
