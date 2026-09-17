<?php

declare(strict_types=1);

namespace App\Modules\X202\Ui;

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

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
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
