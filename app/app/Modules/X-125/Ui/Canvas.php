<?php

declare(strict_types=1);

namespace App\Modules\X125\Ui;

use App\Modules\X125\Models\Flow;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your automations'])]
class Canvas extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $flows = ($this->businessId > 0)
            ? Flow::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-125::canvas', [
            'flows' => $flows,
        ]);
    }
}
