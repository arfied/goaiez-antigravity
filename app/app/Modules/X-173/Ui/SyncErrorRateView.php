<?php

declare(strict_types=1);

namespace App\Modules\X173\Ui;

use App\Modules\X173\Domain\AccountingSyncEngine;
use App\Modules\X173\Models\SyncRun;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Support\Tenancy;
use Livewire\Component;

class SyncErrorRateView extends Component
{
    public ?int $showingConflictsFor = null;

    public function showConflicts(int $syncRunId)
    {
        $this->showingConflictsFor = $syncRunId;
    }

    public function render(AccountingSyncEngine $engine)
    {
        $businessId = Tenancy::idOrFail();
        $runs = SyncRun::where('business_id', $businessId)->get();
        $rates = [];
        
        foreach ($runs as $run) {
            $rates[$run->id] = $engine->errorRate($businessId, $run->id);
        }
        
        $conflicts = collect();
        if ($this->showingConflictsFor) {
            $conflicts = AccountingSyncConflict::where('business_id', $businessId)
                ->where('sync_run_id', $this->showingConflictsFor)->get();
        }

        return view('x-173::sync-error-rate', [
            'runs' => $runs,
            'rates' => $rates,
            'conflicts' => $conflicts,
        ]);
    }
}
