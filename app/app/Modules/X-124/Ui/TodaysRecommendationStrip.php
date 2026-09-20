<?php

declare(strict_types=1);

namespace App\Modules\X124\Ui;

use App\Modules\X124\Actions\AssistantActOnRecommendationAction;
use App\Modules\X124\Models\AssistantRecommendation;
use App\Support\Tenancy;
use Exception;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TodaysRecommendationStrip extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public bool $ready = false;

    public ?string $errorMessage = null;

    public function load(): void
    {
        $this->ready = true;
        $this->errorMessage = null;
    }

    public function accept(int $id, AssistantActOnRecommendationAction $action): void
    {
        $action->handle($this->businessId, $id, 'accepted');
    }

    public function dismiss(int $id, AssistantActOnRecommendationAction $action): void
    {
        $action->handle($this->businessId, $id, 'dismissed');
    }

    public function render()
    {
        if (! $this->ready) {
            return view('x-124::todays-recommendation-strip', ['recs' => collect()]);
        }

        try {
            $recs = ($this->businessId > 0)
                ? AssistantRecommendation::where('business_id', $this->businessId)->where('status', 'active')->get()
                : collect();
        } catch (Exception $e) {
            // A per-test transaction cannot simulate a database failure without dropping the table or purging the connection, which would break the suite.
            $this->errorMessage = 'Failed to load recommendations';
            $recs = collect();
        }

        return view('x-124::todays-recommendation-strip', [
            'recs' => $recs,
        ]);
    }
}
