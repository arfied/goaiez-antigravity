<?php

declare(strict_types=1);

namespace App\Modules\X217\Ui;

use App\Modules\X217\Models\RecruitmentOffer;
use Livewire\Component;

class OfferComposer extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $offers = ($this->businessId > 0)
            ? RecruitmentOffer::where('business_id', $this->businessId)->with('prospect')->get()
            : collect();

        return view('x-217::offer-composer', [
            'offers' => $offers,
        ]);
    }
}
