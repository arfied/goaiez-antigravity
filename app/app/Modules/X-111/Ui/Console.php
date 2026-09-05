<?php

declare(strict_types=1);

namespace App\Modules\X111\Ui;

use App\Modules\X111\Actions\ResolveAlertAction;
use App\Modules\X111\Actions\ResolveTicketAction;
use App\Modules\X111\Models\OperatorAlert;
use App\Modules\X111\Models\TenantTicket;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Console extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        $this->businessId = Tenancy::idOrFail();
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
        $alerts = OperatorAlert::where('business_id', $this->businessId)
            ->where('status', 'open')
            ->orderBy('id', 'desc')
            ->get();

        $tickets = TenantTicket::where('business_id', $this->businessId)
            ->where('status', 'open')
            ->orderBy('id', 'desc')
            ->get();

        return view('x-111::console', [
            'alerts' => $alerts,
            'tickets' => $tickets,
        ]);
    }
}
