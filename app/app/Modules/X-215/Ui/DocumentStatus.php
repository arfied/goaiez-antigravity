<?php

declare(strict_types=1);

namespace App\Modules\X215\Ui;

use App\Modules\X215\Models\SignableDocument;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DocumentStatus extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $docs = ($this->businessId > 0)
            ? SignableDocument::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-215::document-status', [
            'docs' => $docs,
        ]);
    }
}
