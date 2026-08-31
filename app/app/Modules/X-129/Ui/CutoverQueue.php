<?php

declare(strict_types=1);

namespace App\Modules\X129\Ui;

use App\Modules\X129\Models\RedirectMap;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CutoverQueue extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $redirects = ($this->businessId > 0)
            ? RedirectMap::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-129::cutover-queue', [
            'redirects' => $redirects,
        ]);
    }
}
