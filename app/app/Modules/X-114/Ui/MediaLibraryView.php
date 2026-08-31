<?php

declare(strict_types=1);

namespace App\Modules\X114\Ui;

use App\Modules\X114\Models\MediaAsset;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MediaLibraryView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $assets = ($this->businessId > 0)
            ? MediaAsset::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-114::media-library', [
            'assets' => $assets,
        ]);
    }
}
