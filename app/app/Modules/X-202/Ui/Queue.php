<?php

declare(strict_types=1);

namespace App\Modules\X202\Ui;

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

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function enqueueItem(ApprovalDeskEngine $engine): void
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->itemType) || empty($this->subject) || (empty($this->note) && !$this->isErrorOnly)) {
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

    public function render()
    {
        $items = ($this->businessId > 0)
            ? ApprovalItem::where('business_id', $this->businessId)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('x-202::queue', [
            'items' => $items,
        ]);
    }
}
