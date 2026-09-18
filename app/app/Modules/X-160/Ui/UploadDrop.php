<?php

declare(strict_types=1);

namespace App\Modules\X160\Ui;

use App\Modules\X160\Models\Document;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your documents'])]
class UploadDrop extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function render()
    {
        $docs = ($this->businessId > 0)
            ? Document::where('business_id', $this->businessId)->orderByDesc('id')->get()
            : collect();

        return view('x-160::upload-drop', [
            'documents' => $docs,
        ]);
    }
}
