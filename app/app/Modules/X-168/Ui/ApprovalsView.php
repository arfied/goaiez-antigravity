<?php

declare(strict_types=1);

namespace App\Modules\X168\Ui;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X168\Actions\TimesheetApproveAction;
use App\Modules\X168\Models\Timesheet;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Approvals'])]
class ApprovalsView extends Component
{
    #[Locked]
    public int $businessId;

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::id();
    }

    public function approve(int $id): void
    {
        app(TimesheetApproveAction::class)->approve($this->businessId, $id);
    }

    public function approveAll(): void
    {
        $timesheets = $this->getPendingTimesheets();
        $action = app(TimesheetApproveAction::class);
        foreach ($timesheets as $sheet) {
            $action->approve($this->businessId, $sheet->id);
        }
    }

    private function getPendingTimesheets()
    {
        return Timesheet::where('business_id', $this->businessId)
            ->where(function ($query) {
                $query->where('status', 'submitted')
                    ->orWhere(function ($q) {
                        $q->where('status', 'open')
                            ->where('period_end', '<', Carbon::today());
                    });
            })
            ->orderBy('period_start', 'asc')
            ->get();
    }

    public function render(): View
    {
        $timesheets = $this->getPendingTimesheets();

        $ids = $timesheets->pluck('person_id')->unique();
        $names = User::whereIn('id', $ids)->get()->keyBy('id');

        $hours = [];
        foreach ($timesheets as $sheet) {
            $h = floor($sheet->total_hours);
            $m = round(($sheet->total_hours - $h) * 60);
            $hours[$sheet->id] = sprintf('%d:%02d', (int) $h, (int) $m);
        }

        return view('x-168::approvals', [
            'timesheets' => $timesheets,
            'names' => $names,
            'hours' => $hours,
        ]);
    }
}
