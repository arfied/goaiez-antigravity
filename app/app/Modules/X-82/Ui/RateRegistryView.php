<?php

declare(strict_types=1);

namespace App\Modules\X82\Ui;

use App\Enums\UserRole;
use App\Modules\X82\Actions\RateSetAction;
use App\Modules\X82\Models\Rate;
use App\Support\Tenancy;
use Livewire\Component;

class RateRegistryView extends Component
{
    public int $businessId = 0;

    public string $newRateCode = '';

    public string $newAmountDollars = '';

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::SuperAdmin, UserRole::OpsAdmin)), 403);
        $this->businessId = Tenancy::id();
    }

    public function setRate()
    {
        if (empty($this->newRateCode) || empty($this->newAmountDollars)) {
            return;
        }

        $cents = (int) (floatval($this->newAmountDollars) * 100);

        app(RateSetAction::class)->setRate(
            $this->businessId,
            $this->newRateCode,
            $cents
        );

        $this->newRateCode = '';
        $this->newAmountDollars = '';
    }

    public function render()
    {
        $rates = Rate::where('business_id', $this->businessId)->get()->map(function ($rate) {
            $rate->formatted_amount = '$'.number_format($rate->amount_cents / 100, 2);

            return $rate;
        });

        return view('x-82::rate-registry', [
            'rates' => $rates,
        ]);
    }
}
