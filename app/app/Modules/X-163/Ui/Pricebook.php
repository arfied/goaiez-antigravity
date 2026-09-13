<?php

declare(strict_types=1);

namespace App\Modules\X163\Ui;

use App\Enums\UserRole;
use App\Modules\X163\Actions\BookVersionAction;
use App\Modules\X163\Actions\PriceConfirmAction;
use App\Modules\X163\Actions\PriceLookupAction;
use App\Modules\X163\Domain\PricebookEngine;
use App\Modules\X163\Models\CalloutFee;
use App\Modules\X163\Models\LocationBook;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your prices'])]
class Pricebook extends Component
{
    public float $calloutFeeDollars = 0.00;

    // (R245) the deduction property carries the name pricebook.blade.php and updatedCalloutFeeDeducted() bind
    public bool $calloutFeeDeducted = false;

    public string $newServiceName = '';

    public float $newPriceDollars = 0.00;

    public ?float $newMinPriceDollars = null;

    public ?float $newMaxPriceDollars = null;

    public float $newTaxRatePct = 0.00;

    public bool $newIsSample = false;

    public string $testQuery = '';

    public ?array $testQuoteResult = null;

    public array $inlinePrices = [];

    public array $refusals = [];

    public ?int $traceItemId = null;

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
        $businessId = Tenancy::id();
        abort_unless($businessId !== null && $businessId > 0, 403);

        $fee = CalloutFee::where('business_id', $businessId)->first();
        if ($fee) {
            $this->calloutFeeDollars = $fee->callout_fee_cents / 100;
            $this->calloutFeeDeducted = (bool) $fee->deducted_if_proceeding;
        }

        $items = PriceBookItem::where('business_id', $businessId)->get();
        foreach ($items as $item) {
            $this->inlinePrices[$item->id] = $item->price_cents / 100;
        }
    }

    public function updatedCalloutFeeDollars($value): void
    {
        $this->saveCalloutFee();
    }

    public function updatedCalloutFeeDeducted($value): void
    {
        $this->saveCalloutFee();
    }

    public function saveCalloutFee(): void
    {
        $businessId = Tenancy::id();
        $cents = (int) round((float) $this->calloutFeeDollars * 100);

        CalloutFee::updateOrCreate(
            ['business_id' => $businessId],
            [
                'callout_fee_cents' => $cents,
                'deducted_if_proceeding' => $this->calloutFeeDeducted,
            ]
        );
    }

    public function addItem(): void
    {
        if (empty($this->newServiceName)) {
            return;
        }

        $businessId = Tenancy::id();

        if (PriceBookItem::where('business_id', $businessId)->where('service_key', PriceBookItem::serviceKey($this->newServiceName))->whereNull('location_book_id')->exists()) {
            // (R245) addItem() refuses a second business-wide row with the same service_name for the same business, and says so through addError
            $this->addError('newServiceName', 'A business-wide price for this service already exists.');

            return;
        }

        $min = $this->newMinPriceDollars ? (int) round((float) $this->newMinPriceDollars * 100) : null;
        $max = $this->newMaxPriceDollars ? (int) round((float) $this->newMaxPriceDollars * 100) : null;
        $cents = (int) round((float) $this->newPriceDollars * 100);

        // (R245) addItem() confirms a new non-sample price only when PriceConfirmAction::confirmable() admits it
        $confirmed = ! $this->newIsSample && PriceConfirmAction::confirmable($cents);

        $item = PriceBookItem::create([
            'business_id' => $businessId,
            'service_name' => $this->newServiceName,
            'price_cents' => $cents,
            'price_min_cents' => $min,
            'price_max_cents' => $max,
            'tax_rate_pct' => (float) $this->newTaxRatePct,
            'is_sample' => $this->newIsSample,
            'is_confirmed' => $confirmed,
            'confirmed_at' => $confirmed ? now() : null,
        ]);

        if (! $this->newIsSample && ! $confirmed) {
            $this->refusals[$item->id] = true;
        }

        $this->inlinePrices[$item->id] = $item->price_cents / 100;

        $this->newServiceName = '';
        $this->newPriceDollars = 0.00;
        $this->newMinPriceDollars = null;
        $this->newMaxPriceDollars = null;
        $this->newTaxRatePct = 0.00;
        $this->newIsSample = false;
    }

    public function updatePrice(int $id): void
    {
        $businessId = Tenancy::id();
        if (isset($this->inlinePrices[$id])) {
            $newCents = (int) round((float) $this->inlinePrices[$id] * 100);
            $query = PriceBookItem::where('business_id', $businessId)->where('id', $id);
            $row = $query->first();

            if ($row) {
                $payload = ['price_cents' => $newCents];
                if ($row->price_cents !== $newCents) {
                    // (R245) an inline price edit that changes the amount clears the confirmation, and one that does not changes nothing
                    $payload['is_confirmed'] = false;
                    $payload['confirmed_at'] = null;
                }
                $query->update($payload);
            }
        }
    }

    public function confirmItem(int $id): void
    {
        $businessId = Tenancy::id();
        if (isset($this->inlinePrices[$id])) {
            $this->updatePrice($id);
        }

        $confirmer = app(PriceConfirmAction::class);
        $result = $confirmer->handle($businessId, $id);

        if (isset($result['refusal_code']) && $result['refusal_code'] === 'FILL_ME') {
            $this->refusals[$id] = true;
        } else {
            unset($this->refusals[$id]);
        }
    }

    public function deleteItem(int $id): void
    {
        $businessId = Tenancy::id();
        PriceBookItem::where('business_id', $businessId)->where('id', $id)->delete();
        unset($this->inlinePrices[$id]);
        unset($this->refusals[$id]);
    }

    public function tracePrice(int $id): void
    {
        if ($this->traceItemId === $id) {
            $this->traceItemId = null;
        } else {
            $this->traceItemId = $id;
        }
    }

    public function bumpVersion(string $locationName): void
    {
        $businessId = Tenancy::id();
        $action = new BookVersionAction(new PricebookEngine);
        $action->handle($businessId, $locationName);
    }

    public function runTestQuote(): void
    {
        $businessId = Tenancy::id();
        $lookup = new PriceLookupAction(new PricebookEngine);
        $res = $lookup->handle($businessId, $this->testQuery, 'customer');

        $this->testQuoteResult = $res;
    }

    public function render()
    {
        $businessId = Tenancy::id();
        $items = PriceBookItem::where('business_id', $businessId)->orderBy('id', 'desc')->get();
        $locations = LocationBook::where('business_id', $businessId)->get();

        return view('x-163::pricebook', [
            'items' => $items,
            'locations' => $locations,
            'firstLocation' => $locations->first(),
        ]);
    }
}
