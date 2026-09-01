<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Integrations extends Component
{
    public string $stripeStatus = 'connected';

    public string $quickbooksStatus = 'disconnected';

    public string $squareStatus = 'disconnected';

    public string $triggerTiming = 'instant'; // instant, 15m, 2h

    public ?string $testNotification = null;

    public function triggerTestPayment(): void
    {
        $this->testNotification = null;
    }

    public function render(): View
    {
        $webhookUrl = url('/api/v1/webhooks/pos-payments/'.(Tenancy::id() ?? 736));

        return view('livewire.advanced.integrations', [
            'webhookUrl' => $webhookUrl,
        ]);
    }
}
