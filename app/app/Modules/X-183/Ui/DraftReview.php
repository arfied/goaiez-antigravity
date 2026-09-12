<?php

declare(strict_types=1);

namespace App\Modules\X183\Ui;

use App\Modules\X183\Models\ContentDraft;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your drafts'])]
class DraftReview extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $drafts = ($this->businessId > 0)
            ? ContentDraft::where('business_id', $this->businessId)->with('gateResults')->get()
            : collect();

        return view('x-183::draft-review', [
            'drafts' => $drafts,
        ]);
    }
}
