<?php

declare(strict_types=1);

namespace App\Modules\X160\Ui;

use App\Modules\X160\Models\Document;
use Livewire\Attributes\Locked;
use Livewire\Component;

class UploadDrop extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $docs = ($this->businessId > 0)
            ? Document::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-160::upload-drop', [
            'documents' => $docs,
        ]);
    }
}
