<?php

declare(strict_types=1);

namespace App\Modules\X185\Ui;

use App\Modules\X185\Models\ContentPack;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'What is working for businesses like yours'])]
class ExperimentBoard extends Component
{
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id();
    }

    public function render()
    {
        $packs = ContentPack::where('business_id', $this->businessId)
            ->orderByDesc('is_promoted')
            ->orderByDesc('fleet_sample_size')
            ->get();

        return view('x-185::experiment-board', [
            'packs' => $packs,
        ]);
    }
}
