<?php

declare(strict_types=1);

namespace App\Modules\X08\Ui;

use App\Enums\UserRole;
use App\Modules\X08\Actions\ChurnScoreAction;
use App\Modules\X08\Models\ChurnScore;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Churn risk list'])]
class RiskListView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $tenantIdentifier = '';

    public string $loginDecayDays = '';

    public bool $roiOpenRateRising = false;

    public ?string $error = null;

    public ?string $success = null;

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $this->businessId = Tenancy::id();
    }

    public function evaluate(ChurnScoreAction $action)
    {
        $this->error = null;
        $this->success = null;

        if ($this->tenantIdentifier === '') {
            $this->error = 'Tenant Identifier is required.';

            return;
        }

        if ($this->loginDecayDays === '' || ! is_numeric($this->loginDecayDays)) {
            $this->error = 'Login Decay Days must be a number.';

            return;
        }

        try {
            $action->evaluate(
                businessId: Tenancy::idOrFail(),
                tenantIdentifier: $this->tenantIdentifier,
                loginDecayDays: (int) $this->loginDecayDays,
                roiOpenRateRising: $this->roiOpenRateRising
            );

            $this->success = "Evaluated churn score for tenant {$this->tenantIdentifier}.";
            $this->tenantIdentifier = '';
            $this->loginDecayDays = '';
            $this->roiOpenRateRising = false;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $scores = ($this->businessId > 0)
            ? ChurnScore::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-08::risk-list', [
            'scores' => $scores,
        ]);
    }
}
