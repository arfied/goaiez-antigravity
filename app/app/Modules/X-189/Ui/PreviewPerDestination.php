<?php

declare(strict_types=1);

namespace App\Modules\X189\Ui;

use App\Modules\X189\Models\BrandedMedia;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PreviewPerDestination extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $media = ($this->businessId > 0)
            ? BrandedMedia::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-189::preview-per-destination', [
            'media' => $media,
        ]);
    }
}
