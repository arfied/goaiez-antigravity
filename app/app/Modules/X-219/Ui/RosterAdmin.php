<?php

declare(strict_types=1);

namespace App\Modules\X219\Ui;

use App\Modules\X219\Models\AiModel;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RosterAdmin extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $models = ($this->businessId > 0)
            ? AiModel::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-219::roster-admin', [
            'models' => $models,
        ]);
    }
}
