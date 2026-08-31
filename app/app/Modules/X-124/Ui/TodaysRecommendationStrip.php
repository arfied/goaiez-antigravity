<?php

declare(strict_types=1);

namespace App\Modules\X124\Ui;

use App\Modules\X124\Models\AssistantRecommendation;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TodaysRecommendationStrip extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $recs = ($this->businessId > 0)
            ? AssistantRecommendation::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-124::todays-recommendation-strip', [
            'recs' => $recs,
        ]);
    }
}
