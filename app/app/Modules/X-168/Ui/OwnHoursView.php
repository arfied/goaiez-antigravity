<?php

declare(strict_types=1);

namespace App\Modules\X168\Ui;

use App\Enums\UserRole;
use App\Modules\X168\Models\Timesheet;
use App\Modules\X168\Models\TimesheetEntry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your hours'])]
class OwnHoursView extends Component
{
    #[Locked]
    public int $personId;

    public array $expanded = [];

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Staff, UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
        $this->personId = auth()->id();
    }

    public function toggle(int $id): void
    {
        // toggle visibility: if not set, it's expanded by default. So toggle sets it to false.
        // Then next toggle sets it to true.
        $this->expanded[$id] = ! ($this->expanded[$id] ?? true);
    }

    public function render(): View
    {
        $timesheets = Timesheet::where('person_id', $this->personId)
            ->orderBy('period_start', 'desc')
            ->get();

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

        return view('x-168::own-hours', [
            'timesheets' => $timesheets,
            'entries' => $entries,
            'hours' => $hours,
        ]);
    }
}
