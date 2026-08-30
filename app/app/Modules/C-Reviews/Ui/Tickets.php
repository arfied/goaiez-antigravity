<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Ui;

use App\Modules\CReviews\Models\ReviewRequest;
use Livewire\Component;

class Tickets extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $tickets = ($this->businessId > 0)
            ? ReviewRequest::where('business_id', $this->businessId)->where('status', 'triaged_internal')->get()
            : collect();

        return view('c-reviews::tickets', [
            'tickets' => $tickets,
        ]);
    }
}
