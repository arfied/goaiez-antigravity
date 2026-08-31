<?php

declare(strict_types=1);

namespace App\Modules\X212\Ui;

use App\Modules\X212\Models\MigrationRun;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DryrunPreview extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $runs = ($this->businessId > 0)
            ? MigrationRun::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-212::dryrun-preview', [
            'runs' => $runs,
        ]);
    }
}
