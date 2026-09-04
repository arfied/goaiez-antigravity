<?php

declare(strict_types=1);

namespace App\Modules\X102\Ui;

use App\Modules\X102\Models\ChatLead;
use Livewire\Attributes\Locked;
use Livewire\Component;

class OfflineFormInbox extends Component
{
    public function mount(): void {}

    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $leads = ($this->businessId > 0)
            ? ChatLead::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-102::offline-form-inbox', [
            'leads' => $leads,
        ]);
    }
}
