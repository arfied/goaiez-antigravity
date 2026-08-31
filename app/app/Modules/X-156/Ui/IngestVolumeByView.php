<?php

declare(strict_types=1);

namespace App\Modules\X156\Ui;

use App\Modules\X156\Models\IngestRun;
use Livewire\Attributes\Locked;
use Livewire\Component;

class IngestVolumeByView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $runs = ($this->businessId > 0)
            ? IngestRun::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-156::ingest-volume-by', [
            'runs' => $runs,
        ]);
    }
}
