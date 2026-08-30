<?php

declare(strict_types=1);

namespace App\Modules\X180\Ui;

use App\Modules\X180\Models\ContentPack;
use Livewire\Component;

class PackBrowser extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $packs = ($this->businessId > 0)
            ? ContentPack::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-180::pack-browser', [
            'packs' => $packs,
        ]);
    }
}
