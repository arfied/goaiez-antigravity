<?php

declare(strict_types=1);

namespace App\Modules\X186\Ui;

use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X186\Actions\SequenceStopAction;
use App\Modules\X186\Models\CampaignRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Campaigns running now'])]
class LiveRun extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?int $lastStoppedCount = null;

    public ?int $lastStoppedPersonId = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    /** Stops every campaign running for this person — the same stop as "Campaigns stopped or paused" (StopLog::stopRemaining). */
    public function stop(int $personId, SequenceStopAction $action): void
    {
        $this->lastStoppedCount = $action->stopAllSequencesForPerson($this->businessId, $personId, 'manual');
        $this->lastStoppedPersonId = $personId;
    }

    public function render()
    {
        if ($this->businessId <= 0) {
            return view('x-186::live-run', ['runs' => collect(), 'people' => collect()]);
        }

        $runs = CampaignRun::where('business_id', $this->businessId)
            ->where('is_active', true)
            ->where('is_suppressed', false)
            ->orderByDesc('updated_at')
            ->get();

        $people = collect();
        foreach ($runs->pluck('person_id')->unique() as $personId) {
            $row = app(EntityReadAction::class)->handle('people', (int) $personId, $this->businessId);
            if ($row !== null) {
                $people->put((int) $personId, $row);
            }
        }

        return view('x-186::live-run', [
            'runs' => $runs,
            'people' => $people,
        ]);
    }
}
