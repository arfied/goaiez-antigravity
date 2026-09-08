<?php

declare(strict_types=1);

namespace App\Modules\X163\Ui;

use App\Enums\UserRole;
use App\Modules\X163\Actions\PriceConfirmAction;
use App\Modules\X163\Models\CalloutFee;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Livewire\Component;

class ConfirmationScreen extends Component
{
    public float $calloutFeeDollars = 0.0;

    public bool $calloutFeeDeducted = false;

    public array $prices = [];

    public array $refusals = [];

    public function mount()
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $businessId = Tenancy::id();
        abort_unless($businessId !== null && $businessId > 0, 403);

        $callout = CalloutFee::where('business_id', $businessId)->first();
        if ($callout) {
            $this->calloutFeeDollars = $callout->callout_fee_cents / 100;
            $this->calloutFeeDeducted = $callout->deducted_if_proceeding;
        }

        $items = PriceBookItem::where('business_id', $businessId)
            ->where(function ($query) {
                $query->where('is_sample', true)
                    ->orWhere('is_confirmed', false);
            })
            ->get();

        foreach ($items as $item) {
            $this->prices[$item->id] = $item->price_cents / 100;
        }
    }

    public function updatedCalloutFeeDollars($value)
    {
        $businessId = Tenancy::id();
        CalloutFee::updateOrCreate(
            ['business_id' => $businessId],
            ['callout_fee_cents' => (int) round((float) $value * 100)]
        );
    }

    public function updatedCalloutFeeDeducted($value)
    {
        $businessId = Tenancy::id();
        CalloutFee::updateOrCreate(
            ['business_id' => $businessId],
            ['deducted_if_proceeding' => (bool) $value]
        );
    }

    public function updatePrice(int $itemId, $value)
    {
        $businessId = Tenancy::id();
        PriceBookItem::where('business_id', $businessId)->where('id', $itemId)->update(['price_cents' => (int) round((float) $value * 100)]);
    }

    public function confirm(int $itemId)
    {
        $businessId = Tenancy::id();

        $cents = 0;
        if (isset($this->prices[$itemId])) {
            $cents = (int) round((float) $this->prices[$itemId] * 100);
        }

        if ($cents <= 0) {
            $this->refusals[$itemId] = true;

            return;
        }

        if (isset($this->prices[$itemId])) {
            $this->updatePrice($itemId, $this->prices[$itemId]);
        }

        $action = app(PriceConfirmAction::class);
        $result = $action->handle($businessId, $itemId);

        if (isset($result['refusal_code']) && $result['refusal_code'] === 'FILL_ME') {
            $this->refusals[$itemId] = true;
        } else {
            unset($this->refusals[$itemId]);
            unset($this->prices[$itemId]);
        }
    }

    public function deleteItem(int $itemId)
    {
        $businessId = Tenancy::id();
        PriceBookItem::where('business_id', $businessId)->where('id', $itemId)->delete();
        unset($this->prices[$itemId]);
        unset($this->refusals[$itemId]);
    }

    public function openPricebook()
    {
        $this->dispatch('open-pricebook');
    }

    public function render()
    {
        $businessId = Tenancy::id();
        $items = PriceBookItem::where('business_id', $businessId)
            ->where(function ($query) {
                $query->where('is_sample', true)
                    ->orWhere('is_confirmed', false);
            })
            ->get();

        $callout = CalloutFee::where('business_id', $businessId)->first();

        $unconfirmedCount = PriceBookItem::where('business_id', $businessId)->where('is_confirmed', false)->count();
        $isCalloutSet = $callout && $callout->callout_fee_cents > 0;

        return view('x-163::confirmation-screen', [
            'items' => $items,
            'isCalloutSet' => $isCalloutSet,
            'unconfirmedCount' => $unconfirmedCount,
            'calloutFee' => $callout,
        ]);
    }
}
