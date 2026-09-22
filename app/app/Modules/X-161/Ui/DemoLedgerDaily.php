<?php

declare(strict_types=1);

namespace App\Modules\X161\Ui;

use App\Modules\X161\Actions\DemoProvisionAction;
use App\Modules\X161\Actions\DemoResetAction;
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

    public int $resetTenantId = 0;

    public bool $confirmReset = false;

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

    public function resetDemo(DemoResetAction $action): void
    {
        $this->reset('success', 'error');

        if ($this->resetTenantId === 0) {
            $this->error = 'Please select a demo tenant.';

            return;
        }

        $tenant = DemoTenant::where('business_id', Tenancy::idOrFail())
            ->where('id', $this->resetTenantId)
            ->first();

        if ($tenant === null) {
            $this->error = 'Please select a valid demo tenant.';

            return;
        }

        $beforeCount = DemoLedger::where('business_id', Tenancy::idOrFail())
            ->where('demo_tenant_id', $this->resetTenantId)
            ->where('entry_type', 'debit')
            ->count();

        if ($beforeCount === 0) {
            $this->error = 'There are no debits to remove for this demo tenant.';

            return;
        }

        if (! $this->confirmReset) {
            $this->error = 'This removes '.$beforeCount.' debit entries for '.$tenant->demo_slug.'. Tick confirm and press again.';

            return;
        }

        $action->resetDemo(Tenancy::idOrFail(), $this->resetTenantId);

        $afterCount = DemoLedger::where('business_id', Tenancy::idOrFail())
            ->where('demo_tenant_id', $this->resetTenantId)
            ->where('entry_type', 'debit')
            ->count();

        $deleted = $beforeCount - $afterCount;

        $this->success = 'Reset demo debits for '.$tenant->demo_slug.'. Removed '.$deleted.' debit entries. The initial credit allocation is untouched.';
        $this->reset('resetTenantId');
        $this->confirmReset = false;
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
