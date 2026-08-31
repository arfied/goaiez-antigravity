<?php

declare(strict_types=1);

namespace App\Modules\X185\Ui;

use App\Modules\X185\Models\Sequence;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DigestLine extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $sequences = ($this->businessId > 0)
            ? Sequence::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-185::digest-line', [
            'sequences' => $sequences,
        ]);
    }
}
