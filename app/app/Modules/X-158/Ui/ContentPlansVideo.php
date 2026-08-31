<?php

declare(strict_types=1);

namespace App\Modules\X158\Ui;

use App\Modules\X158\Models\Video;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ContentPlansVideo extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $videos = ($this->businessId > 0)
            ? Video::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-158::content-plans-video', [
            'videos' => $videos,
        ]);
    }
}
