<?php

declare(strict_types=1);

namespace App\Modules\X205\Ui;

use App\Modules\X205\Actions\AffiliatePayoutRequestAction;
use App\Modules\X205\Models\AffiliatePayout;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Affiliate payouts'])]
class PayoutRunView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public int $affiliateId = 0;

    public int $amountCents = 0;

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function requestPayout(AffiliatePayoutRequestAction $action): void
    {
        $this->reset(['success', 'error']);

        if ($this->affiliateId <= 0 || $this->amountCents <= 0) {
            $this->error = 'Valid affiliate ID and amount are required.';

            return;
        }

        try {
            $payout = $action->requestPayout(Tenancy::idOrFail(), (int) $this->affiliateId, (int) $this->amountCents);
        } catch (ModelNotFoundException $e) {
            $this->error = 'Affiliate not found for the given ID.';

            return;
        } catch (\InvalidArgumentException $e) {
            $this->error = 'Payout rejected: amount exceeds available current balance. Attribute a sale first if balance is zero.';

            return;
        }

        $this->success = "Requested payout of {$this->amountCents} cents for affiliate #{$this->affiliateId}. Status is '{$payout->status}'. No money moves.";
        $this->reset(['affiliateId', 'amountCents']);
    }

    public function render()
    {
        $payouts = ($this->businessId > 0)
            ? AffiliatePayout::where('business_id', $this->businessId)->orderByDesc('id')->get()
            : collect();

        return view('x-205::payout-run', [
            'payouts' => $payouts,
        ]);
    }
}
