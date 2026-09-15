<?php

declare(strict_types=1);

namespace App\Modules\X102\Ui;

use App\Enums\UserRole;
use App\Modules\X102\Models\ChatLead;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Chat leads'])]
class OfflineFormInbox extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
    }

    #[Locked]
    public int $businessId = 0;

    public function boot(): void
    {
        if ($this->businessId === 0 && Tenancy::id() !== null) {
            $this->businessId = Tenancy::id();
        }
    }

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
