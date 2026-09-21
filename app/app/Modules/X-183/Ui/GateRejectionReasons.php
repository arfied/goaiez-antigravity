<?php

declare(strict_types=1);

namespace App\Modules\X183\Ui;

use App\Modules\X183\Actions\ContentGateAction;
use App\Modules\X183\Models\ContentDraft;
use App\Modules\X183\Models\GateResult;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Why drafts were held back'])]
class GateRejectionReasons extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?int $draftId = null;

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function gateDraft(ContentGateAction $action)
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->draftId)) {
            $this->error = 'Please select a draft.';

            return;
        }

        try {
            $result = $action->evaluateGate(Tenancy::idOrFail(), $this->draftId);
            $this->success = 'Evaluated draft '.$this->draftId.'. '.($result->passed ? 'It passed.' : 'It was rejected: '.$result->rejection_reason).' The draft row\'s own flags were updated; nothing was published anywhere external.';
            $this->draftId = null;
        } catch (ModelNotFoundException $e) {
            $this->error = 'Draft not found.';
        }
    }

    public function render()
    {
        $rejections = ($this->businessId > 0)
            ? GateResult::where('business_id', $this->businessId)->where('passed', false)->get()
            : collect();

        $titles = $rejections->isEmpty()
            ? collect()
            : ContentDraft::where('business_id', $this->businessId)->whereIn('id', $rejections->pluck('draft_id'))->pluck('title', 'id');

        $drafts = ($this->businessId > 0)
            ? ContentDraft::where('business_id', $this->businessId)
                ->whereNotIn('id', GateResult::where('business_id', $this->businessId)->pluck('draft_id'))
                ->get()
            : collect();

        return view('x-183::gate-rejection-reasons', [
            'rejections' => $rejections,
            'titles' => $titles,
            'drafts' => $drafts,
        ]);
    }
}
