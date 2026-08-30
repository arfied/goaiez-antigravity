<?php

declare(strict_types=1);

namespace App\Modules\X160\Ui;

use App\Modules\X160\Models\ExtractionRun;
use Livewire\Component;

class ExtractionErrorRate extends Component
{
    public int $businessId = 0;

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
