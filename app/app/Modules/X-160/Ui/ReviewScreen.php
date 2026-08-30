<?php

declare(strict_types=1);

namespace App\Modules\X160\Ui;

use App\Modules\X160\Models\Document;
use Livewire\Component;

class ReviewScreen extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $pending = ($this->businessId > 0)
            ? Document::where('business_id', $this->businessId)->where('status', 'ingested')->get()
            : collect();

        return view('x-160::review-screen', [
            'pending' => $pending,
        ]);
    }
}
