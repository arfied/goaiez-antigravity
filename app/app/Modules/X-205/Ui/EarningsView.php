<?php

declare(strict_types=1);

namespace App\Modules\X205\Ui;

use App\Modules\X205\Actions\AffiliateAttributeAction;
use App\Modules\X205\Actions\AffiliateProposeClawbackAction;
use App\Modules\X205\Models\AffiliateAttribution;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Affiliate earnings'])]
class EarningsView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $affiliateCode = '';

    public string $orderId = '';

    public int $saleAmountCents = 0;

    public ?string $success = null;

    public ?string $error = null;

    public string $clawbackAttributionId = '';

    public string $clawbackDisputeRef = '';

    public ?string $clawbackSuccess = null;

    public ?string $clawbackError = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function attributeSale(AffiliateAttributeAction $action): void
    {
        $this->reset(['success', 'error']);

        if (trim($this->affiliateCode) === '' || trim($this->orderId) === '') {
            $this->error = 'Affiliate code and order ID are required.';

            return;
        }

        try {
            $attribution = $action->attributeSale(Tenancy::idOrFail(), $this->affiliateCode, $this->orderId, (int) $this->saleAmountCents);
        } catch (ModelNotFoundException $e) {
            $this->error = 'Affiliate not found for the given code.';

            return;
        }

        $this->success = "Attributed order {$this->orderId} to affiliate {$this->affiliateCode}. Commission is {$attribution->commission_cents} cents. This feeds the earnings view and updates the affiliate's balance; nothing downstream is wired to it yet.";
        $this->reset(['affiliateCode', 'orderId', 'saleAmountCents']);
    }

    public function proposeClawback(AffiliateProposeClawbackAction $action): void
    {
        $this->reset(['clawbackSuccess', 'clawbackError']);

        if (trim($this->clawbackAttributionId) === '') {
            $this->clawbackError = 'Choose the commission to claw back.';

            return;
        }

        $attribution = $action->proposeClawback(
            Tenancy::idOrFail(),
            (int) $this->clawbackAttributionId,
            'Customer order refunded',
            trim($this->clawbackDisputeRef) !== '' ? trim($this->clawbackDisputeRef) : null
        );

        $this->clawbackSuccess = 'Clawback proposed on order '.$attribution->order_id
            .'. No money has moved — the '.$attribution->commission_cents.' cent commission is unchanged. '
            .'Nothing approves a proposal yet and nobody is notified, so it stays '.$attribution->clawback_status.'.';
        $this->reset(['clawbackAttributionId', 'clawbackDisputeRef']);
    }

    public function render()
    {
        $attributions = ($this->businessId > 0)
            ? AffiliateAttribution::where('business_id', $this->businessId)->orderByDesc('attributed_at')->get()
            : collect();

        return view('x-205::earnings', [
            'attributions' => $attributions,
        ]);
    }
}
