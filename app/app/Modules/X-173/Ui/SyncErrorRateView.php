<?php

declare(strict_types=1);

namespace App\Modules\X173\Ui;

use App\Modules\X173\Domain\AccountingSyncEngine;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Modules\X173\Models\SyncRun;
use App\Support\Tenancy;
use Livewire\Component;

class SyncErrorRateView extends Component
{
    public ?int $shownRun = null;

    public function show(int $runId): void
    {
        $this->shownRun = $this->shownRun === $runId ? null : $runId;
    }

    public function render(AccountingSyncEngine $engine)
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $runs = SyncRun::where('business_id', $businessId)->orderByDesc('id')->get();

        $rates = [];
        foreach ($runs as $run) {
            $rates[$run->id] = $engine->errorRate($businessId, $run->id);
        }

        $sumSynced = (int) $runs->sum('records_synced');
        $conflicts = (int) $runs->sum('conflicts_count');

        $overall = $engine->rateOf($sumSynced, $conflicts);
        $seen = $sumSynced + $conflicts;

        $shownConflicts = [];
        if ($this->shownRun !== null) {
            $shownConflicts = AccountingSyncConflict::where('business_id', $businessId)
                ->where('sync_run_id', $this->shownRun)
                ->get();
        }

        return view('x-173::sync-error-rate', [
            'runs' => $runs,
            'rates' => $rates,
            'overall' => $overall,
            'seen' => $seen,
            'conflicts' => $conflicts,
            'shownConflicts' => $shownConflicts,
        ]);
    }
}
