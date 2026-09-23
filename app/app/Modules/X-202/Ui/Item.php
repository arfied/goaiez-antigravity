<?php

declare(strict_types=1);

namespace App\Modules\X202\Ui;

use App\Modules\X202\Actions\ApprovalEscalateAction;
use App\Modules\X202\Domain\ApprovalDeskEngine;
use App\Modules\X202\Models\ApprovalItem;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Approval'])]
class Item extends Component
{
    #[Url]
    public int $id = 0;

    public ?string $success = null;

    public ?string $error = null;

    public string $escalateReason = '';

    public string $decisionComment = '';

    public function mount(): void
    {
        abort_if(Tenancy::id() === null, 403);
        // We will just verify it exists and is for this tenant
        ApprovalItem::where('business_id', Tenancy::idOrFail())->findOrFail($this->id);
    }

    public function escalateItem(ApprovalEscalateAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (trim($this->escalateReason) === '') {
            $this->error = 'Say why this needs to go higher before escalating.';

            return;
        }

        $item = ApprovalItem::where('business_id', Tenancy::idOrFail())->findOrFail($this->id);

        if ($item->status !== 'pending') {
            $this->error = 'Only an item still waiting for a decision can be escalated. This one reads '.$item->status.'.';

            return;
        }

        $action->handle(Tenancy::idOrFail(), $this->id, $this->escalateReason);

        $this->success = 'Escalated. '.$item->subject.' stays on this queue, now marked escalated, '
            .'so it is still in front of you. Nobody is notified — there is no manager queue behind '
            .'this yet, so tell whoever needs to decide it.';

        $this->escalateReason = '';
    }

    private function decideItem(ApprovalDeskEngine $engine, string $decision): void
    {
        $this->error = null;
        $this->success = null;

        $item = ApprovalItem::where('business_id', Tenancy::idOrFail())->findOrFail($this->id);

        if ($item->decided_at !== null) {
            $this->error = 'That item was already decided on '.$item->decided_at->toDateString().'. Nothing was changed.';

            return;
        }

        $comment = trim($this->decisionComment) === '' ? null : trim($this->decisionComment);

        $result = $engine->decide(Tenancy::idOrFail(), $item->id, $decision, auth()->id(), $comment);

        if ($result['status'] === 'pending') {
            $this->success = $item->subject.' moved to the next approval step and is still waiting. '
                .'It stays on this queue until the last desk decides.';

            return;
        }

        if ($decision === 'approved') {
            $this->success = $item->subject.' is approved and has moved to the approval history. '
                .'Nothing acts on the decision yet — approving records your answer, it does not carry out '
                .'what was asked.';
        } else {
            $this->success = $item->subject.' is rejected and has moved to the approval history. '
                .'Nothing acts on the decision yet — rejecting records your answer, it does not carry out '
                .'what was asked.';
        }

        $this->decisionComment = '';
    }

    public function approveItem(ApprovalDeskEngine $engine): void
    {
        $this->decideItem($engine, 'approved');
    }

    public function rejectItem(ApprovalDeskEngine $engine): void
    {
        $this->decideItem($engine, 'rejected');
    }

    public function render()
    {
        $item = ApprovalItem::with('chain')
            ->where('business_id', Tenancy::idOrFail())
            ->findOrFail($this->id);

        return view('x-202::item', [
            'item' => $item,
        ]);
    }
}
