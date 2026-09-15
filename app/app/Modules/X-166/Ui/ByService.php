<?php

declare(strict_types=1);

namespace App\Modules\X166\Ui;

use App\Enums\UserRole;
use App\Modules\X166\Actions\MarginReportAction;
use App\Modules\X166\Models\JobCost;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Margin by service'])]
class ByService extends Component
{
    #[Locked]
    public int $businessId;

    public array $expanded = [];

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::id();
    }

    public function toggle(string $id): void
    {
        if (isset($this->expanded[$id])) {
            unset($this->expanded[$id]);
        } else {
            $this->expanded[$id] = true;
        }
    }

    public function render()
    {
        $rows = app(MarginReportAction::class)->handle($this->businessId, 'service');

        $allJobs = JobCost::where('business_id', $this->businessId)
            ->orderBy('job_id')
            ->get()
            ->groupBy('service_type');

        $computedRows = [];
        foreach ($rows as $row) {
            $serviceType = $row['service_type'];

            $totalRevenue = (int) $row['total_revenue'];
            $totalCost = (int) $row['total_cost'];
            $totalMargin = (int) $row['total_margin'];
            $marginPct = ($totalRevenue > 0) ? ($totalMargin / $totalRevenue) * 100 : 0.0;

            $jobs = $allJobs->get($serviceType) ?? collect();

            $computedRows[] = [
                'key' => $serviceType,
                'service_name' => $serviceType,
                'jobs_count' => $jobs->count(),
                'jobs' => $jobs,
                'revenue' => number_format($totalRevenue / 100, 2, '.', ''),
                'cost' => number_format($totalCost / 100, 2, '.', ''),
                'margin' => number_format($totalMargin / 100, 2, '.', ''),
                'margin_pct' => $marginPct,
                'margin_pct_formatted' => number_format($marginPct, 2, '.', '').' %',
            ];
        }

        return view('x-166::by-service', [
            'computedRows' => $computedRows,
        ]);
    }
}
