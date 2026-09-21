<?php

declare(strict_types=1);

namespace App\Modules\X161\Ui;

use App\Modules\X161\Actions\DemoProvisionAction;
use App\Modules\X161\Domain\DemoSandboxEngine;
use App\Modules\X161\Models\DemoLedger;
use App\Modules\X161\Models\DemoTenant;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DemoLedgerDaily extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $prospectDomain = '';

    public int $demoTenantId = 0;

    public string $message = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function provisionDemo(DemoProvisionAction $action): void
    {
        $this->reset('success', 'error');

        if (trim($this->prospectDomain) === '') {
            $this->error = 'Please provide a prospect domain.';

            return;
        }

        $tenant = $action->provisionDemo(Tenancy::idOrFail(), $this->prospectDomain);

        $this->success = 'Provisioned demo for '.$tenant->demo_slug.'. This is a mock/sandbox demo — no real tenant, no real money.';
        $this->reset('prospectDomain');
    }

    public function sendTestMessage(DemoSandboxEngine $engine): void
    {
        $this->reset('success', 'error');

        if ($this->demoTenantId === 0 || trim($this->message) === '') {
            $this->error = 'Please select a demo tenant and provide a message.';

            return;
        }

        $result = $engine->sendSandboxMessage(Tenancy::idOrFail(), $this->demoTenantId, $this->message);

        $this->success = 'Recorded a mock debit. Carrier reached: '.($result['carrier_reached'] ? 'true' : 'false').'.';
        $this->reset('demoTenantId', 'message');
    }

    public function render()
    {
        $entries = ($this->businessId > 0)
            ? DemoLedger::where('business_id', $this->businessId)->get()
            : collect();

        $tenants = ($this->businessId > 0)
            ? DemoTenant::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-161::demo-ledger-daily', [
            'entries' => $entries,
            'tenants' => $tenants,
        ]);
    }
}
