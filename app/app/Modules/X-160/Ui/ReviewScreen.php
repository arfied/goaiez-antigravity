<?php

declare(strict_types=1);

namespace App\Modules\X160\Ui;

use App\Modules\X160\Models\Document;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Documents to review'])]
class ReviewScreen extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function render()
    {
        $pending = ($this->businessId > 0)
            ? Document::where('business_id', $this->businessId)->where('status', 'ingested')->orderByDesc('id')->get()
            : collect();

        return view('x-160::review-screen', [
            'pending' => $pending,
        ]);
    }
}
