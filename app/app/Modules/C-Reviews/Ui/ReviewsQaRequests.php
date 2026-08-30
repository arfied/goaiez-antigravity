<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Ui;

use App\Modules\CReviews\Models\ReviewRequest;
use Livewire\Component;

class ReviewsQaRequests extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $requests = ($this->businessId > 0)
            ? ReviewRequest::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('c-reviews::reviews-qa-requests', [
            'requests' => $requests,
        ]);
    }
}
