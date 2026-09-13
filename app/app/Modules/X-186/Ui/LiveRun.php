<?php

declare(strict_types=1);

namespace App\Modules\X186\Ui;

use App\Modules\X121\Models\Person;
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

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        if ($this->businessId <= 0) {
            return view('x-186::live-run', ['runs' => collect(), 'people' => collect()]);
        }

        $runs = CampaignRun::where('business_id', $this->businessId)
            ->where('is_active', true)
            ->where('is_suppressed', false)
            ->whereNull('stopped_reason')
            ->orderByDesc('updated_at')
            ->get();

        $personIds = $runs->pluck('person_id')->unique()->values()->toArray();
        $people = Person::whereIn('id', $personIds)->get()->keyBy('id');

        return view('x-186::live-run', [
            'runs' => $runs,
            'people' => $people,
        ]);
    }
}
