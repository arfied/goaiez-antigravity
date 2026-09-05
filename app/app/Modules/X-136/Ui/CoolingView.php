<?php

declare(strict_types=1);

namespace App\Modules\X136\Ui;

use App\Modules\X136\Actions\SignalListAction;
use App\Modules\X136\Models\SignalScore;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CoolingView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public ?string $actionFailed = null;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $this->businessId = Tenancy::id() ?: 0;
            if ($this->businessId <= 0) {
                abort(403, 'Tenant context is required');
            }
        }
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
    }

    public function markDecayed(string $prospectIdentifier, SignalListAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $this->actionFailed = null;

        try {
            $action->markDecayed($this->businessId, $prospectIdentifier, 30);
        } catch (\Exception $e) {
            $this->actionFailed = $e->getMessage();
        }
    }

    public function render()
    {
        if ($this->isSample) {
            $scores = collect([
                (object) ['prospect_identifier' => 'John Doe', 'cooling_status' => 'cooling', 'signal_value' => 60.5],
                (object) ['prospect_identifier' => 'Jane Smith', 'cooling_status' => 'decayed', 'signal_value' => 45.0],
            ]);
        } else {
            $scores = ($this->businessId > 0)
                ? SignalScore::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
                : collect();
        }

        return view('x-136::cooling-view', [
            'scores' => $scores,
        ]);
    }
}
