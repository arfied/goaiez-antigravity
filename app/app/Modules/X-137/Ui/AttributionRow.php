<?php

declare(strict_types=1);

namespace App\Modules\X137\Ui;

use App\Modules\X137\Models\CallToken;
use Livewire\Component;

class AttributionRow extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $tokens = ($this->businessId > 0)
            ? CallToken::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-137::attribution-row', [
            'tokens' => $tokens,
        ]);
    }
}
