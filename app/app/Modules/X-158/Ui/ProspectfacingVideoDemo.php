<?php

declare(strict_types=1);

namespace App\Modules\X158\Ui;

use App\Modules\X158\Models\Video;
use Livewire\Component;

class ProspectfacingVideoDemo extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $video = ($this->businessId > 0)
            ? Video::where('business_id', $this->businessId)->latest('id')->first()
            : null;

        return view('x-158::prospectfacing-video-demo', [
            'video' => $video,
        ]);
    }
}
