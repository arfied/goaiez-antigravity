<?php

declare(strict_types=1);

namespace App\Modules\X164\Ui;

use App\Modules\X164\Actions\EstimateAcceptAction;
use App\Modules\X164\Actions\EstimateDraftAction;
use App\Modules\X164\Actions\EstimateRefreshAction;
use App\Modules\X164\Actions\EstimateSendAction;
use App\Modules\X164\Models\Estimate;
use App\Modules\X164\Models\EstimateLine;
use App\Modules\X172\Actions\PortalLinkAction;
use App\Services\Config\DefaultsRegistry;
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

    public string $refreshedUnitPriceCents = '';

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

        $estimate = Estimate::where('business_id', Tenancy::idOrFail())->findOrFail($estimateId);
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

    public function refreshEstimate(int $estimateId, EstimateRefreshAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (trim($this->refreshedUnitPriceCents) === '' || ! ctype_digit(trim($this->refreshedUnitPriceCents))) {
            $this->error = 'Type the new unit price in whole cents before refreshing.';

            return;
        }

        $estimate = Estimate::where('business_id', Tenancy::idOrFail())->findOrFail($estimateId);

        if ($estimate->status !== 'expired') {
            $this->error = 'Only an expired estimate can be refreshed. This one reads '.ucfirst($estimate->status).'.';

            return;
        }

        $line = EstimateLine::where('business_id', Tenancy::idOrFail())
            ->where('estimate_id', $estimate->id)
            ->firstOrFail();

        // The action matches lines by service_name, so we pass the row's own name and not a typed one.
        $refreshed = $action->handle(
            Tenancy::idOrFail(),
            $estimate->id,
            $estimate->price_book_version + 1,
            [[
                'service_name' => $line->service_name,
                'quantity' => $line->quantity,
                'unit_price_cents' => (int) $this->refreshedUnitPriceCents,
            ]],
        );

        $this->success = 'Refreshed. Estimate '.$refreshed->estimate_number.' is back to Sent at $'
            .number_format($refreshed->total_cents / 100, 2).', priced from book version '
            .$refreshed->price_book_version.' and valid for another 7 days. '
            .'The customer is not told, and the deposit figure moved with the total — nothing has been charged.';

        $this->refreshedUnitPriceCents = '';
    }

    public array $portalUrl = [];   // estimate id → public URL

    public function portalLink(int $estimateId, PortalLinkAction $action): void
    {
        $estimate = Estimate::where('business_id', Tenancy::idOrFail())->findOrFail($estimateId);
        $link = $action->handle((int) Tenancy::idOrFail(), 'estimate', (int) $estimate->id, $estimate->customer_id === null ? null : (int) $estimate->customer_id);
        $this->portalUrl[$estimateId] = route('x-172.portal', ['token' => $link->token]);
    }

    public function render()
    {
        $estimates = ($this->businessId > 0)
            ? Estimate::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('x-164::estimates-list', [
            'estimates' => $estimates,
            'portalTtlHours' => app(DefaultsRegistry::class)->int('portal.link.ttl_hours'),
        ]);
    }
}
