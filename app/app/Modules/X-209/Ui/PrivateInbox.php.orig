<?php

declare(strict_types=1);

namespace App\Modules\X209\Ui;

use App\Modules\X209\Models\FixerCommand;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Fixer inbox'])]
class PrivateInbox extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function render()
    {
        $commands = ($this->businessId > 0)
            ? FixerCommand::where('business_id', $this->businessId)->orderByDesc('id')->get()
            : collect();

        return view('x-209::private-inbox', [
            'commands' => $commands,
        ]);
    }
}
