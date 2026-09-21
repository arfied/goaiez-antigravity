<?php

declare(strict_types=1);

namespace App\Modules\X153\Ui;

use App\Modules\X153\Actions\AlertClaimAction;
use App\Modules\X153\Actions\AlertOverrideAction;
use App\Modules\X153\Models\AlertClaim;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Claim expiry'])]
class ClaimexpiryRate extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $code = '';

    public ?string $success = null;

    public ?string $error = null;

    public int $overrideAlertId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function claimAlert(AlertClaimAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if (trim($this->code) === '') {
            $this->error = 'Code is required.';

            return;
        }

        $result = $action->handle(Tenancy::idOrFail(), $this->code, auth()->id() ?? 1);

        if ($result['status'] === 'claimed') {
            $this->success = 'Claimed alert '.$result['alert_id'].'. This updates the claim list; nothing downstream is wired to it yet.';
            $this->code = '';
        } else {
            $this->error = $result['message'];
        }
    }

    public function takeOverAlert(AlertOverrideAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if ($this->overrideAlertId === 0) {
            $this->error = 'Please select an alert to take over.';

            return;
        }

        $claim = AlertClaim::where('business_id', Tenancy::idOrFail())
            ->where('alert_id', $this->overrideAlertId)
            ->first();

        if ($claim === null) {
            $this->error = 'No claim found for this alert.';

            return;
        }

        if ($claim->claimed_by_user_id === auth()->id()) {
            $this->error = 'You already hold the claim for this alert.';

            return;
        }

        $result = $action->handle(Tenancy::idOrFail(), $this->overrideAlertId, auth()->id());

        $this->success = 'Alert #'.$this->overrideAlertId.' is now claimed by you.';
        $this->overrideAlertId = 0;
    }

    public function render()
    {
        $claims = collect();
        $active = 0;
        $expired = 0;
        $rate = 0;

        if ($this->businessId > 0) {
            $claims = AlertClaim::where('business_id', $this->businessId)
                ->orderByDesc('claimed_at')
                ->limit(50)
                ->get();

            $active = AlertClaim::where('business_id', $this->businessId)->where('status', 'active')->count();
            $expired = AlertClaim::where('business_id', $this->businessId)->where('status', 'expired')->count();
            $total = $active + $expired;

            if ($total > 0) {
                $rate = (int) round(($expired / $total) * 100);
            }
        }

        return view('x-153::claimexpiry-rate', [
            'claims' => $claims,
            'active' => $active,
            'expired' => $expired,
            'rate' => $rate,
        ]);
    }
}
