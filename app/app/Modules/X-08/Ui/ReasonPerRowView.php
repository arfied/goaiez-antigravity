<?php

declare(strict_types=1);

namespace App\Modules\X08\Ui;

use App\Enums\UserRole;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Reason Per Row'])]
class ReasonPerRowView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $this->businessId = Tenancy::id();
    }

    public function render()
    {
        return view('x-08::reason-per-row');
    }
}
