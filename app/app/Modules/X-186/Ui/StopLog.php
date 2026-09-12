<?php

declare(strict_types=1);

namespace App\Modules\X186\Ui;

use App\Modules\X121\Models\Person;
use App\Modules\X186\Actions\SequenceStopAction;
use App\Modules\X186\Models\CampaignRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Campaigns stopped or paused'])]
class StopLog extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $failed = false;

    public ?int $lastStoppedCount = null;

    public ?int $lastStoppedPersonId = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function stopRemaining(int $personId, SequenceStopAction $action): void
    {
        try {
            $count = $action->stopAllSequencesForPerson($this->businessId, $personId, 'manual');
            $this->lastStoppedCount = $count;
            $this->lastStoppedPersonId = $personId;
        } catch (\Throwable $e) {
            $this->failed = true;
        }
    }

    public function render()
    {
        if ($this->businessId <= 0 || $this->failed) {
            return view('x-186::stop-log', ['runs' => collect(), 'people' => collect()]);
        }

        $runs = CampaignRun::where('business_id', $this->businessId)
            ->where(function ($query) {
                $query->whereNotNull('stopped_reason')
                    ->orWhere('is_suppressed', true);
            })
            ->orderByDesc('updated_at')
            ->get();

        $personIds = $runs->pluck('person_id')->unique()->values()->toArray();
        $people = Person::whereIn('id', $personIds)->get()->keyBy('id');

        return view('x-186::stop-log', [
            'runs' => $runs,
            'people' => $people,
        ]);
    }
}
