<?php

declare(strict_types=1);

namespace App\Modules\X140\Ui;

use App\Modules\X140\Models\ContentTopic;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProposedPagesView extends Component
{
    public function mount(): void {}

    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $topics = ($this->businessId > 0)
            ? ContentTopic::where('business_id', $this->businessId)->with('sources')->get()
            : collect();

        return view('x-140::proposed-pages', [
            'topics' => $topics,
        ]);
    }
}
