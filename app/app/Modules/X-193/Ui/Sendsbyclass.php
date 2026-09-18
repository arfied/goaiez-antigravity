<?php

declare(strict_types=1);

namespace App\Modules\X193\Ui;

use App\Modules\X193\Models\NotificationClass;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Sends by class'])]
class Sendsbyclass extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $classes = ($this->businessId > 0)
            ? NotificationClass::where('business_id', $this->businessId)->orderBy('caller_type')->get()
            : collect();

        return view('x-193::sendsbyclass', [
            'classes' => $classes,
        ]);
    }
}
