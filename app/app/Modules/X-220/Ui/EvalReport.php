<?php

declare(strict_types=1);

namespace App\Modules\X220\Ui;

use App\Modules\X220\Models\GoldenSet;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EvalReport extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $sets = ($this->businessId > 0)
            ? GoldenSet::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-220::eval-report', [
            'sets' => $sets,
        ]);
    }
}
