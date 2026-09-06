<?php

declare(strict_types=1);

namespace App\Modules\X163\Ui;

use App\Enums\UserRole;
use App\Modules\X163\Actions\PriceConfirmAction;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Livewire\Component;

// (R245) the daily digest lists SAMPLE refusals flagged on the item (§140.1); a NO_FACT agent refusal about pricebook creates a price_cents=0, is_confirmed=false row so the owner sees the gap in the daily digest.
// (R245) the daily pricing digest is a screen rendered on demand from the refusal flags; no scheduled send this wave
class DailyPricingDigest extends Component
{
    public array $refusals = [];

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $businessId = Tenancy::id();
        abort_unless($businessId !== null && $businessId > 0, 403);
    }

    public function confirm(int $itemId)
    {
        $businessId = Tenancy::id();
        $result = app(PriceConfirmAction::class)->handle($businessId, $itemId);

        if (isset($result['refusal_code']) && $result['refusal_code'] === 'FILL_ME') {
            $this->refusals[$itemId] = true;
        } else {
            unset($this->refusals[$itemId]);
        }
    }

    public function render()
    {
        $businessId = Tenancy::id();

        $items = PriceBookItem::where('business_id', $businessId)
            ->whereNotNull('refusal_flagged_at')
            ->where('is_confirmed', false)
            ->orderByDesc('refusal_flagged_at')
            ->orderByDesc('refusal_count')
            ->get();

        return view('x-163::daily-pricing-digest', [
            'items' => $items,
        ]);
    }
}
