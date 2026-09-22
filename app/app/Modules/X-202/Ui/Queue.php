<?php

declare(strict_types=1);

namespace App\Modules\X202\Ui;

use App\Modules\X202\Actions\ApprovalEscalateAction;
use App\Modules\X202\Domain\ApprovalDeskEngine;
use App\Modules\X202\Models\ApprovalItem;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Approvals'])]
class Queue extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?string $itemType = null;

    public ?string $subject = null;

    public ?string $note = null;

    public bool $isErrorOnly = false;

    public ?string $success = null;

    public ?string $error = null;

    public string $escalateReason = '';

    public string $decisionComment = '';

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function enqueueItem(ApprovalDeskEngine $engine): void
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->itemType) || empty($this->subject) || (empty($this->note) && ! $this->isErrorOnly)) {
            $this->error = 'Please fill out all required fields.';

            return;
        }

        $payload = ['note' => $this->note];
        if ($this->isErrorOnly) {
            $payload['error_only'] = true;
        }

        $result = $engine->enqueue(
            Tenancy::idOrFail(),
            $this->itemType,
            $this->subject,
            $payload
        );

        if (($result['status'] ?? null) === 'refused') {
            $this->error = $result['message'] ?? 'Refused';

            return;
        }

        $this->success = 'Enqueued an item waiting for a decision; nothing downstream is wired to it yet.';

        $this->itemType = null;
        $this->subject = null;
        $this->note = null;
        $this->isErrorOnly = false;
    }

    public function escalateItem(int $itemId, ApprovalEscalateAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (trim($this->escalateReason) === '') {
            $this->error = 'Say why this needs to go higher before escalating.';

            return;
        }

        $item = ApprovalItem::where('business_id', Tenancy::idOrFail())->findOrFail($itemId);

        if ($item->status !== 'pending') {
            $this->error = 'Only an item still waiting for a decision can be escalated. This one reads '.$item->status.'.';

            return;
        }

        $action->handle(Tenancy::idOrFail(), $itemId, $this->escalateReason);

        $this->success = 'Escalated. '.$item->subject.' stays on this queue, now marked escalated, '
            .'so it is still in front of you. Nobody is notified — there is no manager queue behind '
            .'this yet, so tell whoever needs to decide it.';

        $this->escalateReason = '';
    }

    private function decideItem(int $itemId, ApprovalDeskEngine $engine, string $decision): void
    {
        $this->error = null;
        $this->success = null;

        $item = ApprovalItem::where('business_id', Tenancy::idOrFail())->findOrFail($itemId);

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

    public function approveItem(int $itemId, ApprovalDeskEngine $engine): void
    {
        $this->decideItem($itemId, $engine, 'approved');
    }

    public function rejectItem(int $itemId, ApprovalDeskEngine $engine): void
    {
        $this->decideItem($itemId, $engine, 'rejected');
    }

    public function render()
    {
        $items = ($this->businessId > 0)
            ? ApprovalItem::where('business_id', $this->businessId)
                ->whereIn('status', ['pending', 'escalated', 'expired'])
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('x-202::queue', [
            'items' => $items,
        ]);
    }
}
