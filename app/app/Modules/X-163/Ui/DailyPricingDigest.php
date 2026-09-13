<?php

declare(strict_types=1);

namespace App\Modules\X163\Ui;

use App\Enums\UserRole;
use App\Modules\X163\Actions\PriceConfirmAction;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
// (R245) the daily digest lists SAMPLE refusals flagged on the item (§140.1); a NO_FACT agent refusal about pricebook creates a price_cents=0, is_confirmed=false row so the owner sees the gap in the daily digest.
// (R245) the daily pricing digest is a screen rendered on demand from the refusal flags; no scheduled send this wave
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Daily pricing digest'])]
class DailyPricingDigest extends Component
{
    public array $refusals = [];

    public array $prices = [];

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $businessId = Tenancy::id();
        abort_unless($businessId !== null && $businessId > 0, 403);
    }

    private function updatePrice(int $itemId, $value)
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

        $result = app(PriceConfirmAction::class)->handle($businessId, $itemId);

        if (isset($result['refusal_code']) && $result['refusal_code'] === 'FILL_ME') {
            $this->refusals[$itemId] = true;
        } else {
            unset($this->refusals[$itemId]);
            unset($this->prices[$itemId]);
        }
    }

    public function render()
    {
        $businessId = Tenancy::id();

        $items = PriceBookItem::where('business_id', $businessId)

            ->whereNotNull('refusal_flagged_at')
            ->where('is_confirmed', false)
            ->orderByDesc('refusal_count')
            ->orderByDesc('refusal_flagged_at')
            ->get();

        foreach ($items as $item) {
            if (! isset($this->prices[$item->id])) {
                $this->prices[$item->id] = $item->price_cents / 100;
            }
        }

        return view('x-163::daily-pricing-digest', [
            'items' => $items,
        ]);
    }
}
