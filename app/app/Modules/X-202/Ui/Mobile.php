<?php

declare(strict_types=1);

namespace App\Modules\X202\Ui;

use App\Modules\X202\Domain\ApprovalDeskEngine;
use App\Modules\X202\Models\ApprovalItem;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Mobile extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
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

        $result = $engine->decide(Tenancy::idOrFail(), $item->id, $decision, auth()->id());

        if ($result['status'] === 'pending') {
            $this->success = $item->subject.' moved to the next approval step and is still waiting.';

            return;
        }

        if ($decision === 'approved') {
            $this->success = $item->subject.' is approved. Nothing acts on the decision yet — approving '
                .'records your answer, it does not carry out what was asked.';
        } else {
            $this->success = $item->subject.' is rejected. Nothing acts on the decision yet — rejecting '
                .'records your answer, it does not carry out what was asked.';
        }
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
                ->whereIn('status', ['pending', 'escalated'])
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('x-202::mobile', [
            'items' => $items,
        ]);
    }
}
