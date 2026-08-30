<?php

declare(strict_types=1);

namespace App\Modules\X158\Ui;

use App\Modules\X158\Models\Video;
use Livewire\Component;

class RenderQueue extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $queue = ($this->businessId > 0)
            ? Video::where('business_id', $this->businessId)->where('is_rendered', false)->get()
            : collect();

        return view('x-158::render-queue', [
            'queue' => $queue,
        ]);
    }
}
