<?php

declare(strict_types=1);

namespace App\Modules\X102\Ui;

use App\Modules\X102\Models\ChatTurn;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Chat thread'])]
class Thread extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        return view('x-102::thread', [
            'turns' => ($this->businessId > 0) ? ChatTurn::where('business_id', $this->businessId)->orderBy('id')->get() : collect(),
        ]);
    }
}
