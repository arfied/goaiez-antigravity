<?php

declare(strict_types=1);

namespace App\Modules\X124\Ui;

use App\Modules\X124\Actions\AssistantActOnRecommendationAction;
use App\Modules\X124\Models\AssistantRecommendation;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TodaysRecommendationStrip extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function accept(int $id, AssistantActOnRecommendationAction $action)
    {
        $action->handle($this->businessId, $id, 'accepted');
    }

    public function dismiss(int $id, AssistantActOnRecommendationAction $action)
    {
        $action->handle($this->businessId, $id, 'dismissed');
    }

    public function render()
    {
        $recs = ($this->businessId > 0)
            ? AssistantRecommendation::where('business_id', $this->businessId)->where('status', 'active')->get()
            : collect();

        return view('x-124::todays-recommendation-strip', [
            'recs' => $recs,
        ]);
    }
}
