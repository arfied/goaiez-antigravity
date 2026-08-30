<?php

declare(strict_types=1);

namespace App\Modules\X105\Ui;

use App\Modules\X105\Models\OutreachLadder;
use Livewire\Component;

class PipelineBoard extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $ladders = ($this->businessId > 0)
            ? OutreachLadder::where('business_id', $this->businessId)->with('steps')->get()
            : collect();

        return view('x-105::pipeline-board', [
            'ladders' => $ladders,
        ]);
    }
}
