<?php

declare(strict_types=1);

namespace App\Modules\X207\Ui;

use App\Modules\X207\Actions\PushRetireDeviceAction;
use App\Modules\X207\Models\DeviceToken;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Retired devices'])]
class RetirementReasons extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $deviceToken = '';

    public string $reason = 'unregistered';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        abort_if($this->businessId === 0, 404);
    }

    public function submit(PushRetireDeviceAction $action): void
    {
        $this->reset(['success', 'error']);

        if (empty($this->deviceToken)) {
            $this->error = 'Device token is required.';

            return;
        }

        try {
            $action->handle($this->businessId, $this->deviceToken, $this->reason);
            $this->success = 'Retired device token. This feeds the retirement list; nothing downstream is wired to it yet.';
            $this->reset(['deviceToken']);
            $this->reason = 'unregistered';
        } catch (ModelNotFoundException $e) {
            $this->error = 'Device token not found: '.$this->deviceToken;
        }
    }

    public function render()
    {
        return view('x-207::retirement-reasons', [
            'tokens' => ($this->businessId > 0) ? DeviceToken::where('business_id', $this->businessId)->where('status', 'retired')->orderByDesc('id')->get() : collect(),
        ]);
    }
}
