<?php

declare(strict_types=1);

namespace App\Modules\X156\Ui;

use App\Modules\X156\Models\IngestRejection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RejectedrowsListView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $rejections = ($this->businessId > 0)
            ? IngestRejection::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-156::rejectedrows-list', [
            'rejections' => $rejections,
        ]);
    }
}
