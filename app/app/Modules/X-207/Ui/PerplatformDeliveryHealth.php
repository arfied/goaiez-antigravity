<?php

declare(strict_types=1);

namespace App\Modules\X207\Ui;

use App\Modules\X207\Actions\PushRegisterDeviceAction;
use App\Modules\X207\Models\DeviceToken;
use App\Modules\X207\Models\PushDelivery;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Push health'])]
class PerplatformDeliveryHealth extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $deviceToken = '';

    public string $platform = 'ios';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        abort_if($this->businessId === 0, 404);
    }

    public function submit(PushRegisterDeviceAction $action): void
    {
        $this->reset(['success', 'error']);

        if (empty($this->deviceToken)) {
            $this->error = 'Device token is required.';

            return;
        }

        $action->handle(
            $this->businessId,
            $this->deviceToken,
            $this->platform,
            null
        );

        $this->success = 'Registered device token. This feeds the push list; nothing downstream is wired to it yet.';
        $this->reset(['deviceToken']);
        $this->platform = 'ios';
    }

    public function render()
    {
        return view('x-207::perplatform-delivery-health', [
            'platforms' => ($this->businessId > 0) ? DeviceToken::where('business_id', $this->businessId)->selectRaw('platform, COUNT(*) as devices')->groupBy('platform')->orderBy('platform')->get() : collect(),
            'statuses' => ($this->businessId > 0) ? PushDelivery::where('business_id', $this->businessId)->selectRaw('status, COUNT(*) as n')->groupBy('status')->orderBy('status')->get() : collect(),
        ]);
    }
}
