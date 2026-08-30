<?php

declare(strict_types=1);

namespace App\Modules\X194\Ui;

use App\Modules\X194\Models\SavedView;
use Livewire\Component;

class SavedViewsList extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $views = ($this->businessId > 0)
            ? SavedView::all()->where('business_id', $this->businessId)
            : collect();

        return view('x-194::saved-views-list', [
            'views' => $views,
        ]);
    }
}
