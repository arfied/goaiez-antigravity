<?php

declare(strict_types=1);

namespace App\Modules\X168\Ui;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X168\Actions\TimesheetReopenAction;
use App\Modules\X168\Models\Timesheet;
use App\Modules\X168\Models\TimesheetEntry;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Timesheets'])]
class TimesheetsView extends Component
{
    #[Locked]
    public int $businessId;

    public array $expanded = [];

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::id();
    }

    public function toggle(int $id): void
    {
        $this->expanded[$id] = ! ($this->expanded[$id] ?? false);
    }

    public function reopen(int $id): void
    {
        app(TimesheetReopenAction::class)->reopen($this->businessId, $id);
    }

    public function render(): View
    {
        $timesheets = Timesheet::where('business_id', $this->businessId)
            ->orderBy('period_start', 'desc')
            ->get();

        $ids = $timesheets->pluck('person_id')->unique();
        $names = User::whereIn('id', $ids)->get()->keyBy('id');

        $timesheetIds = $timesheets->pluck('id');
        $entries = TimesheetEntry::whereIn('timesheet_id', $timesheetIds)
            ->orderBy('started_at')
            ->get()
            ->groupBy('timesheet_id');

        $hours = [];
        foreach ($timesheets as $sheet) {
            $h = floor($sheet->total_hours);
            $m = round(($sheet->total_hours - $h) * 60);
            $hours[$sheet->id] = sprintf('%d:%02d', (int) $h, (int) $m);
        }

        return view('x-168::timesheets', [
            'timesheets' => $timesheets,
            'names' => $names,
            'entries' => $entries,
            'hours' => $hours,
        ]);
    }
}
