<?php

declare(strict_types=1);

namespace App\Modules\X108\Ui;

use App\Modules\X108\Models\Waitlist as WaitlistModel;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your waitlist'])]
class Waitlist extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $waitlists = ($this->businessId > 0)
            ? WaitlistModel::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-108::waitlist', [
            'waitlists' => $waitlists,
        ]);
    }
}
