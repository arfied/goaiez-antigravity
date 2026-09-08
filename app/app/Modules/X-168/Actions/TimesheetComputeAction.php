<?php

declare(strict_types=1);

namespace App\Modules\X168\Actions;

use App\Modules\X168\Events\PeriodReady;
use App\Modules\X168\Events\TimesheetSubmitted;
use App\Modules\X168\Models\Timesheet;
use App\Modules\X168\Models\TimesheetEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class TimesheetComputeAction
{
    /**
     * Records a job-state window GPS location ping and updates timesheet.
     * 1. Reads/writes GPS location ONLY inside an active job-state window (TEST ANCHOR).
     * 2. A location event with NO active job writes NOTHING (TEST ANCHOR).
     */
    public function recordJobWindow(
        int $businessId,
        int $personId,
        ?int $jobId,
        string $stateWindow,
        Carbon $startedAt,
        ?Carbon $endedAt = null,
        ?float $locationLat = null,
        ?float $locationLng = null
    ): ?TimesheetEntry {
        // 1. Guard: A location event with no active job writes nothing (TEST ANCHOR)
        if (empty($jobId) || ! in_array($stateWindow, ['en_route', 'on_site'], true)) {
            // Location event outside active job window is discarded with zero database writes (TEST ANCHOR)
            return null;
        }

        // Active job window -> find or create open timesheet for the week
        $periodStart = $startedAt->copy()->startOfWeek()->toDateString();
        $periodEnd = $startedAt->copy()->endOfWeek()->toDateString();

        $timesheet = Timesheet::firstOrCreate(
            ['business_id' => $businessId, 'person_id' => $personId, 'period_start' => $periodStart],
            ['period_end' => $periodEnd, 'total_hours' => 0.00, 'status' => 'open']
        );

        $durationMinutes = 0;
        if ($endedAt) {
            $durationMinutes = max(0, (int) $startedAt->diffInMinutes($endedAt));
        }

        $entry = TimesheetEntry::create([
            'business_id' => $businessId,
            'timesheet_id' => $timesheet->id,
            'job_id' => $jobId,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
            'state_window' => $stateWindow,
            'location_lat' => $locationLat,
            'location_lng' => $locationLng,
        ]);

        $this->updateTotalHoursAndDispatch($businessId, $timesheet);

        return $entry;
    }

    public function closeJobWindow(int $businessId, int $jobId, ?Carbon $endedAt = null): ?TimesheetEntry
    {
        $entry = TimesheetEntry::where('business_id', $businessId)
            ->where('job_id', $jobId)
            ->whereNull('ended_at')
            ->latest('id')
            ->first();

        if (! $entry) {
            return null;
        }

        $endedAt = $endedAt ?? Carbon::now();
        $durationMinutes = max(0, (int) $entry->started_at->diffInMinutes($endedAt));

        $entry->update([
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
        ]);

        $timesheet = Timesheet::find($entry->timesheet_id);
        $this->updateTotalHoursAndDispatch($businessId, $timesheet);

        return $entry;
    }

    private function updateTotalHoursAndDispatch(int $businessId, Timesheet $timesheet): void
    {
        $totalMinutes = TimesheetEntry::where('timesheet_id', $timesheet->id)->sum('duration_minutes');
        $totalHours = round($totalMinutes / 60, 2);
        $timesheet->update(['total_hours' => $totalHours]);

        Event::dispatch(new TimesheetSubmitted($businessId, $timesheet->id, $totalHours));
        Event::dispatch(new PeriodReady($businessId, $timesheet->id, $timesheet->period_end->toDateString()));

    }
}
