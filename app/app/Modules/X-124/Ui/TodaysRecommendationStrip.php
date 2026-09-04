<?php

declare(strict_types=1);

namespace App\Modules\X124\Ui;

use App\Modules\X124\Actions\AssistantExecuteAction;
use App\Modules\X124\Actions\AssistantPreviewAction;
use App\Modules\X124\Models\AssistantRecommendation;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TodaysRecommendationStrip extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public bool $isSample = false;

    #[Locked]
    public ?string $loadError = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId;
    }

    public array $previewData = [];

    public ?int $previewingId = null;

    public bool $previewIsConfirmed = false;

    public function preview(int $id): void
    {
        $rec = AssistantRecommendation::where('business_id', $this->businessId)->findOrFail($id);

        $previewAction = new AssistantPreviewAction;
        $this->previewData = $previewAction->handle($this->businessId, $rec->action_key);
        $this->previewingId = $id;
        $this->previewIsConfirmed = false;
    }

    public function execute(int $id): void
    {
        $rec = AssistantRecommendation::where('business_id', $this->businessId)->findOrFail($id);

        $executeAction = new AssistantExecuteAction;
        $res = $executeAction->handle(
            businessId: $this->businessId,
            actionKey: $rec->action_key,
            isConfirmed: $this->previewIsConfirmed
        );

        if (($res['status'] ?? '') === 'refused_confirmation_required') {
            $this->previewIsConfirmed = true;

            return;
        }

        if ($res['executed'] ?? false) {
            $rec->update(['status' => 'executed']);
            $this->previewingId = null;
            $this->previewData = [];
            $this->previewIsConfirmed = false;
        }
    }

    public function dismiss(int $id): void
    {
        $rec = AssistantRecommendation::where('business_id', $this->businessId)->findOrFail($id);
        $rec->update(['status' => 'dismissed']);
        if ($this->previewingId === $id) {
            $this->previewingId = null;
            $this->previewData = [];
        }
    }

    public function render()
    {
        $recs = ($this->businessId > 0)
            ? AssistantRecommendation::where('business_id', $this->businessId)
                ->where('status', 'active')
                ->get()
            : collect();

        return view('x-124::todays-recommendation-strip', [
            'recs' => $recs,
        ]);
    }
}
