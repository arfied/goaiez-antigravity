<?php

declare(strict_types=1);

namespace App\Modules\X160\Ui;

use App\Modules\X160\Models\ExtractionRun;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ExtractionErrorRate extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $failedCount = ($this->businessId > 0)
            ? ExtractionRun::where('business_id', $this->businessId)->where('run_status', 'failed')->count()
            : 0;

        return view('x-160::extraction-error-rate', [
            'failedCount' => $failedCount,
        ]);
    }
}
