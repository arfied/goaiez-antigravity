<?php

declare(strict_types=1);

namespace App\Modules\X210\Ui;

use App\Modules\X210\Models\PromotionRedemption;
use App\Modules\X210\Actions\PromotionApplyAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Discounts given'])]
class EarnedVsGivenPanel extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $code = '';
    public string $customerId = '';
    public string $orderId = '';
    public string $orderAmountCents = '';
    
    public string $success = '';
    public string $error = '';

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_if($this->businessId === 0, 404);
    }

    public function redeem(PromotionApplyAction $action): void
    {
        $this->reset(['success', 'error']);

        if (trim($this->code) === '') {
            $this->error = 'Code is required.';
            return;
        }

        try {
            $redemption = $action->applyPromotion(
                Tenancy::idOrFail(),
                $this->code,
                (int) $this->customerId,
                $this->orderId,
                (int) $this->orderAmountCents
            );
            $this->success = "Promotion applied. Discount computed: {$redemption->discount_applied_cents} cents. This feeds the margin lists; nothing downstream is wired to it yet.";
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $totalDiscount = ($this->businessId > 0)
            ? PromotionRedemption::where('business_id', $this->businessId)->sum('discount_applied_cents')
            : 0;

        return view('x-210::earnedvsgiven-panel', [
            'totalDiscount' => $totalDiscount,
        ]);
    }
}
