<?php

declare(strict_types=1);

namespace App\Modules\X150\Ui;

use App\Modules\X150\Actions\ProviderColdAction;
use App\Modules\X150\Models\ProviderAttempt;
use App\Modules\X150\Models\ProviderRoster;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Provider cost per valid record'])]
class ProviderCostPer extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public ?string $actionNotice = null;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $tenantId = Tenancy::id() ?: 0;
            if ($tenantId <= 0) {
                abort(403, 'Tenant context is required');
            }
            $this->businessId = (int) $tenantId;
        }
        Tenancy::set($this->businessId);
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
        $this->actionNotice = null;
    }

    public function markCold(int $id): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        try {
            app(ProviderColdAction::class)->handle($this->businessId, $id, false);
            $this->actionNotice = 'Provider marked cold';
        } catch (\Exception $e) {
            $this->actionNotice = $e->getMessage();
        }
    }

    public function warmUp(int $id): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        try {
            app(ProviderColdAction::class)->handle($this->businessId, $id, true);
            $this->actionNotice = 'Provider warmed up';
        } catch (\Exception $e) {
            $this->actionNotice = $e->getMessage();
        }
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        if ($this->isSample) {
            $roster = collect([
                (object) [
                    'id' => 9401,
                    'provider_name' => 'FastLookup LowCost',
                    'tier_level' => 1,
                    'cost_per_lookup_cents' => 5,
                    'is_active' => true,
                    'attempts' => 10,
                    'valid' => 8,
                    'junk' => 2,
                    'spend_cents' => 50,
                    'cost_per_valid_cents' => 6,
                ],
                (object) [
                    'id' => 9402,
                    'provider_name' => 'DeepEnrich Premium',
                    'tier_level' => 2,
                    'cost_per_lookup_cents' => 45,
                    'is_active' => true,
                    'attempts' => 2,
                    'valid' => 1,
                    'junk' => 1,
                    'spend_cents' => 90,
                    'cost_per_valid_cents' => 90,
                ],
            ]);
        } else {
            $dbRoster = ProviderRoster::where('business_id', $this->businessId)->orderBy('tier_level')->get();
            $roster = $dbRoster->map(function (ProviderRoster $p): object {
                $attempts = ProviderAttempt::where('business_id', $this->businessId)
                    ->where('provider_id', $p->id)
                    ->count();

                $valid = ProviderAttempt::where('business_id', $this->businessId)
                    ->where('provider_id', $p->id)
                    ->where('status', 'success')
                    ->count();

                $junk = ProviderAttempt::where('business_id', $this->businessId)
                    ->where('provider_id', $p->id)
                    ->where('status', 'rejected_junk')
                    ->count();

                $spend_cents = $attempts * (int) $p->cost_per_lookup_cents;
                $cost_per_valid_cents = $valid > 0 ? intdiv($spend_cents, $valid) : null;

                return (object) [
                    'id' => (int) $p->id,
                    'provider_name' => (string) $p->provider_name,
                    'tier_level' => (int) $p->tier_level,
                    'cost_per_lookup_cents' => (int) $p->cost_per_lookup_cents,
                    'is_active' => (bool) $p->is_active,
                    'attempts' => $attempts,
                    'valid' => $valid,
                    'junk' => $junk,
                    'spend_cents' => $spend_cents,
                    'cost_per_valid_cents' => $cost_per_valid_cents,
                ];
            });
        }

        $totalSpendCents = $roster->sum('spend_cents');
        $totalValid = $roster->sum('valid');

        return view('x-150::provider-cost-per', [
            'roster' => $roster,
            'totalSpendCents' => $totalSpendCents,
            'totalValid' => $totalValid,
        ]);
    }
}
