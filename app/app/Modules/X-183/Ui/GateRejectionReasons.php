<?php

declare(strict_types=1);

namespace App\Modules\X183\Ui;

use App\Modules\X183\Models\ContentDraft;
use App\Modules\X183\Models\GateResult;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Why drafts were held back'])]
class GateRejectionReasons extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $rejections = ($this->businessId > 0)
            ? GateResult::where('business_id', $this->businessId)->where('passed', false)->get()
            : collect();

        $titles = $rejections->isEmpty()
            ? collect()
            : ContentDraft::where('business_id', $this->businessId)->whereIn('id', $rejections->pluck('draft_id'))->pluck('title', 'id');

        return view('x-183::gate-rejection-reasons', [
            'rejections' => $rejections,
            'titles' => $titles,
        ]);
    }
}
