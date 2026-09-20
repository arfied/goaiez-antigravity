<?php

declare(strict_types=1);

namespace App\Modules\X166\Ui;

use App\Enums\UserRole;
use App\Modules\X166\Actions\JobCostAction;
use App\Modules\X166\Models\JobCost;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Margin by job'])]
class MarginByJob extends Component
{
    #[Locked]
    public int $businessId;

    public array $expanded = [];

    public ?string $jobId = '';

    public string $priceBookVersion = '';

    public string $laborCostCents = '';

    public string $materialsCostCents = '';

    public string $overheadCostCents = '';

    public string $revenueCents = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::id();
    }

    public function recordJobCost(JobCostAction $action): void
    {
        $this->error = null;
        $this->success = null;

        $jobId = (int) $this->jobId;
        $revenueCents = (int) $this->revenueCents;

        if (! $jobId) {
            $this->error = 'Job ID is required.';

            return;
        }
        if ($revenueCents <= 0) {
            $this->error = 'Revenue must be positive.';

            return;
        }

        try {
            $cost = $action->handle(
                businessId: Tenancy::idOrFail(),
                jobId: $jobId,
                priceBookVersion: $this->priceBookVersion,
                laborCostCents: (int) $this->laborCostCents,
                materialsCostCents: (int) $this->materialsCostCents,
                overheadCostCents: (int) $this->overheadCostCents,
                revenueCents: $revenueCents
            );
            $this->success = 'Recorded job cost for job '.$cost->job_id.'. This feeds the margin lists, but it is not wired to invoice creation or payroll yet.';

            $this->jobId = '';
            $this->priceBookVersion = '';
            $this->laborCostCents = '';
            $this->materialsCostCents = '';
            $this->overheadCostCents = '';
            $this->revenueCents = '';
        } catch (\Throwable $e) {
            $this->error = 'Error recording job cost: '.$e->getMessage();
        }
    }

    public function toggle(int $id): void
    {
        if (isset($this->expanded[$id])) {
            unset($this->expanded[$id]);
        } else {
            $this->expanded[$id] = true;
        }
    }

    public function render()
    {
        $costs = JobCost::where('business_id', $this->businessId)
            ->orderByDesc('id')
            ->get();

        $money = [];
        $pct = [];

        foreach ($costs as $c) {
            $money[$c->id] = [
                'revenue' => number_format($c->revenue_cents / 100, 2, '.', ''),
                'total_cost' => number_format($c->total_cost_cents / 100, 2, '.', ''),
                'margin' => number_format($c->gross_margin_cents / 100, 2, '.', ''),
                'labor' => number_format($c->labor_cost_cents / 100, 2, '.', ''),
                'materials' => number_format($c->materials_cost_cents / 100, 2, '.', ''),
                'overhead' => number_format($c->overhead_cost_cents / 100, 2, '.', ''),
            ];
            $pct[$c->id] = number_format((float) $c->gross_margin_pct, 2, '.', '').' %';
        }

        return view('x-166::margin-by-job', [
            'costs' => $costs,
            'money' => $money,
            'pct' => $pct,
        ]);
    }
}
