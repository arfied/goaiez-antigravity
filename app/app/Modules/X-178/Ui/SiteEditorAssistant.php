<?php

declare(strict_types=1);

namespace App\Modules\X178\Ui;

use App\Enums\UserRole;
use App\Modules\X178\Models\DesignChange;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Site editor'])]
class SiteEditorAssistant extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
        $this->businessId = Tenancy::id() ?? 0;
    }

    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $changes = ($this->businessId > 0)
            ? DesignChange::where('business_id', $this->businessId)->orderByDesc('id')->get()
            : collect();

        return view('x-178::site-editor-assistant', [
            'changes' => $changes,
        ]);
    }
}
