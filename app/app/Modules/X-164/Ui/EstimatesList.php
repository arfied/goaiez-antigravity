<?php

declare(strict_types=1);

namespace App\Modules\X164\Ui;

use App\Modules\X164\Actions\EstimateDraftAction;
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
