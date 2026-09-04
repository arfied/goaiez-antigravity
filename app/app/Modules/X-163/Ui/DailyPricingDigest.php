<?php

declare(strict_types=1);

namespace App\Modules\X163\Ui;

use App\Enums\UserRole;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Component;

// (R245) the daily digest lists SAMPLE refusals flagged on the item (§140.1); a NO_FACT refusal has no item and is not persisted this wave
// (R245) the daily pricing digest is a screen rendered on demand from the refusal flags; no scheduled send this wave
class DailyPricingDigest extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $businessId = Tenancy::id();
        abort_unless($businessId !== null && $businessId > 0, 403);
    }

    public function openConfirmation()
    {
        $this->dispatch('open-confirmation');
    }

    public function render()
    {
        $businessId = Tenancy::id();

        $items = PriceBookItem::where('business_id', $businessId)
            ->whereNotNull('refusal_flagged_at')
            ->whereDate('refusal_flagged_at', Carbon::today())
            ->orderByDesc('refusal_count')
            ->get();

        return view('x-163::daily-pricing-digest', [
            'items' => $items,
        ]);
    }
}
