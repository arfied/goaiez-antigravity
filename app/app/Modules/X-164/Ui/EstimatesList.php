<?php

declare(strict_types=1);

namespace App\Modules\X164\Ui;

use App\Modules\X164\Actions\EstimateAcceptAction;
use App\Modules\X164\Actions\EstimateDraftAction;
use App\Modules\X164\Actions\EstimateSendAction;
use App\Modules\X164\Models\Estimate;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your estimates'])]
class EstimatesList extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $customerSignature = '';

    public string $serviceName = '';

    public int $quantity = 1;

    public int $unitPriceCents = 0;

    public ?string $error = null;

    public ?string $success = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function draftEstimate(EstimateDraftAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (empty($this->serviceName)) {
            $this->error = 'Service name cannot be empty.';

            return;
        }

        if ($this->quantity < 1) {
            $this->error = 'Quantity must be at least 1.';

            return;
        }

        $estimate = $action->handle(
            Tenancy::idOrFail(),
            null,
            [
                [
                    'service_name' => $this->serviceName,
                    'quantity' => $this->quantity,
                    'unit_price_cents' => $this->unitPriceCents,
                ],
            ]
        );

        $formattedTotal = number_format($estimate->total_cents / 100, 2);
        $this->success = "Drafted estimate {$estimate->estimate_number} for \${$formattedTotal}.";

        $this->serviceName = '';
        $this->quantity = 1;
        $this->unitPriceCents = 0;
    }

    public function sendEstimate(int $estimateId, EstimateSendAction $action): void
    {
        $this->error = null;
        $this->success = null;

        $estimate = $action->handle(Tenancy::idOrFail(), $estimateId);

        $this->success = 'Estimate '.$estimate->estimate_number.' is marked sent. Nothing is delivered '
            .'to the customer yet — this records the status only.';
    }

    public function acceptEstimate(int $estimateId, EstimateAcceptAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (trim($this->customerSignature) === '') {
            $this->error = 'Type the customer’s name as their signature before accepting.';

            return;
        }

        $estimate = Estimate::findOrFail($estimateId);
        $estimateNumber = $estimate->estimate_number;

        $result = $action->handle(Tenancy::idOrFail(), $estimateId, trim($this->customerSignature));

        if ($result['status'] === 'expired_locked') {
            $this->error = 'That estimate had already expired, so it was not accepted — and it now reads Expired in the list. Draft a fresh one.';

            return;
        }

        $this->success = 'Accepted. Estimate '.$estimateNumber.' is signed by '.trim($this->customerSignature)
            .' and its price-book version is frozen, so later price changes will not move it. '
            .'No deposit has been requested and no money has moved. A signed PDF is not produced yet.';

        $this->customerSignature = '';
    }

    public function render()
    {
        $estimates = ($this->businessId > 0)
            ? Estimate::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('x-164::estimates-list', [
            'estimates' => $estimates,
        ]);
    }
}
