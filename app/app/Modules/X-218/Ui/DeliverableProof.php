<?php

declare(strict_types=1);

namespace App\Modules\X218\Ui;

use App\Modules\X218\Models\Deliverable;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DeliverableProof extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $deliverables = ($this->businessId > 0)
            ? Deliverable::where('business_id', $this->businessId)->where('is_verified', true)->get()
            : collect();

        return view('x-218::deliverable-proof', [
            'deliverables' => $deliverables,
        ]);
    }
}
