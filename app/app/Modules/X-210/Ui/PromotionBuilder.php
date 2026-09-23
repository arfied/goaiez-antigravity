<?php

declare(strict_types=1);

namespace App\Modules\X210\Ui;

use App\Modules\X210\Actions\PromotionCreateAction;
use App\Support\Tenancy;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Create an offer'])]
class PromotionBuilder extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $code = '';

    public string $discountType = 'percentage';

    public string $amount = '';

    public string $maxUses = '';

    public string $measurementWindowDays = '14';

    public string $saved = '';

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function save(PromotionCreateAction $action): void
    {
        $this->saved = '';
        $this->code = strtoupper(trim($this->code));

        $rules = [
            'code' => ['required', 'max:32', 'regex:/^[A-Z0-9]+$/', Rule::unique('promotions', 'code')->where('business_id', $this->businessId)],
            'discountType' => ['required', 'in:percentage,fixed_cents'],
            'amount' => $this->discountType === 'percentage' ? ['required', 'integer', 'min:1', 'max:100'] : ['required', 'numeric', 'min:0.01', 'max:100000'],
            'maxUses' => ['required', 'integer', 'min:1'],
            'measurementWindowDays' => ['required', 'integer', 'min:1'],
        ];

        $messages = [
            'code.required' => 'Type the code your customers will use.',
            'code.max' => 'Keep the code to 32 letters and numbers or fewer.',
            'code.regex' => 'Use only letters and numbers in the code, with no spaces.',
            'code.unique' => 'You already have an offer with this code.',
            'discountType.required' => 'Choose a percent off or a dollar amount off.',
            'discountType.in' => 'Choose a percent off or a dollar amount off.',
            'amount.required' => 'Type how much the offer takes off.',
            'amount.integer' => 'A percent off is a whole number, like 20.',
            'amount.numeric' => 'Type the dollar amount as a number, like 15.00.',
            'amount.min' => 'The offer has to take something off.',
            'amount.max' => 'That is more than an offer can take off.',
            'maxUses.required' => 'An offer needs a limit of at least one use.',
            'maxUses.integer' => 'An offer needs a limit of at least one use.',
            'maxUses.min' => 'An offer needs a limit of at least one use.',
            'measurementWindowDays.required' => 'An offer needs a measurement window to track incrementality.',
            'measurementWindowDays.integer' => 'The measurement window must be a number of days.',
            'measurementWindowDays.min' => 'The measurement window must be at least 1 day.',
        ];

        $this->validate($rules, $messages);

        if ($this->businessId === 0) {
            $this->addError('code', 'We could not tell which business this offer is for, so nothing was saved.');

            return;
        }

        $action->createPromotion(
            businessId: $this->businessId,
            code: $this->code,
            discountValue: $this->discountType === 'percentage' ? (int) $this->amount : (int) round((float) $this->amount * 100),
            discountType: $this->discountType,
            maxRedemptions: (int) $this->maxUses,
            measurementWindowDays: (int) $this->measurementWindowDays,
        );

        $this->saved = $this->code;
        $this->reset('code', 'amount', 'maxUses');
        $this->measurementWindowDays = '14';
    }

    public function render()
    {
        return view('x-210::promotion-builder');
    }
}
