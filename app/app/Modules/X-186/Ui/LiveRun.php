<?php

declare(strict_types=1);

namespace App\Modules\X186\Ui;

use App\Modules\X121\Actions\EntityReadAction;
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
