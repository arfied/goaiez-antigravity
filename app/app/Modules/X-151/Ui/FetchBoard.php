<?php

declare(strict_types=1);

namespace App\Modules\X151\Ui;

use App\Modules\X151\Models\Fetch;
use Livewire\Component;

class FetchBoard extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $fetches = ($this->businessId > 0)
            ? Fetch::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-151::fetch-board', [
            'fetches' => $fetches,
        ]);
    }
}
