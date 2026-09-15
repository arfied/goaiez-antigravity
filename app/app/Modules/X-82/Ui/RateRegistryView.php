<?php

declare(strict_types=1);

namespace App\Modules\X82\Ui;

use App\Enums\UserRole;
use App\Modules\X82\Actions\RateSetAction;
use App\Modules\X82\Models\Rate;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your rates'])]
class RateRegistryView extends Component
{
    #[Locked]
    public int $businessId;

    public string $newRateCode = '';

    public string $newAmountDollars = '';

    public array $amountInput = [];

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin, UserRole::OpsAdmin)), 403);
        $this->businessId = Tenancy::id();
    }

    public function setRate()
    {
        if (empty($this->newRateCode) || empty($this->newAmountDollars)) {
            return;
        }

        $cents = (int) round((float) $this->newAmountDollars * 100);

        app(RateSetAction::class)->setRate(
            $this->businessId,
            $this->newRateCode,
            $cents
        );

        $this->newRateCode = '';
        $this->newAmountDollars = '';
    }

    public function setInlineRate(int $rateId)
    {
        if (empty($this->amountInput[$rateId])) {
            $this->addError('amountInput.'.$rateId, 'Enter the amount first.');

            return;
        }

        $rate = Rate::find($rateId);
        if (! $rate || $rate->business_id !== $this->businessId) {
            return;
        }

        $cents = (int) round((float) $this->amountInput[$rateId] * 100);

        app(RateSetAction::class)->setRate(
            $this->businessId,
            $rate->rate_code,
            $cents,
            $rate->currency
        );

        $this->amountInput[$rateId] = '';
    }

    public function render()
    {
        $rates = Rate::with('versions')->where('business_id', $this->businessId)->get()->map(function ($rate) {
            $rate->formatted_amount = '$'.number_format($rate->amount_cents / 100, 2);

            $rate->versions->each(function ($version) {
                $version->formatted_amount = '$'.number_format($version->amount_cents / 100, 2);
            });

            return $rate;
        });

        return view('x-82::rate-registry', [
            'rates' => $rates,
        ]);
    }
}
