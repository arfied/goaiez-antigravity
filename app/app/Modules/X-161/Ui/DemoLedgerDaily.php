<?php

declare(strict_types=1);

namespace App\Modules\X161\Ui;

use App\Modules\X161\Actions\DemoProvisionAction;
use App\Modules\X161\Models\DemoLedger;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DemoLedgerDaily extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $prospectDomain = '';
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

        $this->success = 'Provisioned demo for ' . $tenant->demo_slug . '. This is a mock/sandbox demo — no real tenant, no real money.';
        $this->reset('prospectDomain');
    }

    public function render()
    {
        $entries = ($this->businessId > 0)
            ? DemoLedger::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-161::demo-ledger-daily', [
            'entries' => $entries,
        ]);
    }
}
