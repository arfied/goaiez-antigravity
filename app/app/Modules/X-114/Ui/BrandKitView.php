<?php

declare(strict_types=1);

namespace App\Modules\X114\Ui;

use App\Modules\X114\Models\BrandKit;
use Livewire\Component;

class BrandKitView extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $brandKit = ($this->businessId > 0)
            ? BrandKit::where('business_id', $this->businessId)->first()
            : null;

        return view('x-114::brand-kit', [
            'brandKit' => $brandKit,
        ]);
    }
}
