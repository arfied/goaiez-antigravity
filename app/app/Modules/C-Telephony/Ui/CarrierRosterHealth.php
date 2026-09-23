<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Ui;

use App\Modules\CTelephony\Actions\CarrierHealthAction;
use App\Modules\CTelephony\Models\CarrierHealth;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CarrierRosterHealth extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $carrierName = '';

    public string $status = 'healthy';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function recordHealth(CarrierHealthAction $action): void
    {
        if (empty($this->carrierName) || empty($this->status)) {
            $this->error = 'Carrier name and status are required.';

            return;
        }

        $this->error = null;
        $health = $action->updateStatus(Tenancy::idOrFail(), $this->carrierName, $this->status);

        $this->success = 'Recorded health for '.$health->carrier_name.'. This edits the existing row if one exists. This feeds the status list; nothing downstream is wired to it yet.';
        $this->carrierName = '';
    }

    public function render()
    {
        $health = ($this->businessId > 0)
            ? CarrierHealth::where('business_id', $this->businessId)->get()
            : collect();

        return view('c-telephony::carrier-roster-health', [
            'health' => $health,
        ]);
    }
}
