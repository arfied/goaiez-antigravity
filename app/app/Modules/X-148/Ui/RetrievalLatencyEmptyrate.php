<?php

declare(strict_types=1);

namespace App\Modules\X148\Ui;

use App\Modules\X148\Models\RetrievalCache;
use Livewire\Component;

class RetrievalLatencyEmptyrate extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $cacheEntries = ($this->businessId > 0)
            ? RetrievalCache::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-148::retrieval-latency-emptyrate', [
            'entries' => $cacheEntries,
        ]);
    }
}
