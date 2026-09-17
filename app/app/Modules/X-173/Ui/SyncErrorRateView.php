<?php

declare(strict_types=1);

namespace App\Modules\X173\Ui;

use App\Modules\X173\Domain\AccountingSyncEngine;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Modules\X173\Models\SyncRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Sync error rate'])]
class SyncErrorRateView extends Component
{
    public ?int $shownRun = null;

    public function show(int $runId): void
    {
        $this->shownRun = $this->shownRun === $runId ? null : $runId;
    }

    /**
     * A rate above zero never renders as 0%: round() sends anything under half a percent
     * to zero, and this screen promises that a line which could not be placed is a
     * conflict and never a silent gap.
     */
    private function conflictRateLabel(float $rate): string
    {
        $percent = (int) round($rate * 100);

        return $percent === 0 && $rate > 0 ? 'under 1% conflicts' : $percent.'% conflicts';
    }

    public function render(AccountingSyncEngine $engine)
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $runs = SyncRun::where('business_id', $businessId)->orderByDesc('id')->get();

        $rates = [];
        $rateLabels = [];
        foreach ($runs as $run) {
            $rate = $engine->errorRate($businessId, $run->id);
            $rates[$run->id] = $rate;
            $rateLabels[$run->id] = $rate === null ? 'nothing to sync' : $this->conflictRateLabel($rate);
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
            'rateLabels' => $rateLabels,
            'overall' => $overall,
            'overallLabel' => $overall === null ? 'nothing synced yet' : $this->conflictRateLabel($overall),
            'seen' => $seen,
            'conflicts' => $conflicts,
            'shownConflicts' => $shownConflicts,
        ]);
    }
}
