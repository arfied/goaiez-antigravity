<?php

declare(strict_types=1);

namespace App\Modules\X200\Ui;

use App\Modules\X200\Models\CallDisposition;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Wallboard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $dispositions = ($this->businessId > 0)
            ? CallDisposition::where('business_id', $this->businessId)->latest()->take(20)->get()
            : collect();

        return view('x-200::wallboard', [
            'dispositions' => $dispositions,
        ]);
    }
}
