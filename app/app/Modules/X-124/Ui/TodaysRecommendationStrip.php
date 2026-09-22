<?php

declare(strict_types=1);

namespace App\Modules\X124\Ui;

use App\Modules\X124\Actions\AssistantActOnRecommendationAction;
use App\Modules\X124\Actions\AssistantRecommendAction;
use App\Modules\X124\Models\AssistantRecommendation;
use App\Modules\X124\Models\AssistantSession;
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

    public int $selectedSessionId = 0;

    public string $recommendTitle = '';

    public string $actionKey = '';

    public ?string $success = null;

    public ?string $error = null;

    public function recommend(AssistantRecommendAction $action): void
    {
        $this->error = null;
        $this->success = null;
        if (empty($this->recommendTitle) || empty($this->actionKey) || $this->selectedSessionId === 0) {
            $this->error = 'Session, title, and action key are required.';

            return;
        }

        $action->handle(Tenancy::idOrFail(), $this->selectedSessionId, $this->recommendTitle, $this->actionKey);
        $this->success = 'Recorded recommendation. This feeds the recommendations strip; nothing downstream is wired to it yet.';
        $this->recommendTitle = '';
        $this->actionKey = '';
        $this->selectedSessionId = 0;
    }

    public function render()
    {
        if (! $this->ready) {
            return view('x-124::todays-recommendation-strip', ['recs' => collect(), 'sessions' => collect()]);
        }

        try {
            $recs = ($this->businessId > 0)
                ? AssistantRecommendation::where('business_id', $this->businessId)->where('status', 'active')->get()
                : collect();
            $sessions = ($this->businessId > 0)
                ? AssistantSession::where('business_id', $this->businessId)->get()
                : collect();
        } catch (Exception $e) {
            // A per-test transaction cannot simulate a database failure without dropping the table or purging the connection, which would break the suite.
            $this->errorMessage = 'Failed to load recommendations';
            $recs = collect();
            $sessions = collect();
        }

        return view('x-124::todays-recommendation-strip', [
            'recs' => $recs,
            'sessions' => $sessions,
        ]);
    }
}
