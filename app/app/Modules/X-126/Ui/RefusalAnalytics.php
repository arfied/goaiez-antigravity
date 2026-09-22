<?php

declare(strict_types=1);

namespace App\Modules\X126\Ui;

use App\Enums\UserRole;
use App\Modules\X126\Actions\GetCapabilityDecisionsAction;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Capability refusals'])]
class RefusalAnalytics extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager) || auth()->user()->can(AdminAccess::GATE)), 403);
        $this->businessId = Tenancy::id();
    }

    public function render(GetCapabilityDecisionsAction $action)
    {
        $id = $this->businessId;
        if ($id === 0) {
            abort_unless(auth()->check() && Tenancy::check(), 403);
            $id = Tenancy::idOrFail();
        }

        $refusals = ($id > 0)
            ? $action->handle($id)
            : collect();

        return view('x-126::refusal-analytics', [
            'refusals' => $refusals,
        ]);
    }
}
