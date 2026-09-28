<?php

declare(strict_types=1);

namespace App\Modules\X219\Ui;

use App\Enums\UserRole;
use App\Modules\X219\Actions\RosterSeedAction;
use App\Modules\X219\Models\AiModel;
use App\Modules\X219\Models\AiProvider;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Model Roster Administration'])]
class RosterAdmin extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function seed(RosterSeedAction $action): void
    {
        $counts = $action->handle($this->businessId);

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => "Seeded {$counts['providers']} providers and {$counts['models']} models",
        ]);
    }

    public function render()
    {
        $models = ($this->businessId > 0)
            ? AiModel::where('business_id', $this->businessId)->get()
            : collect();

        $providers = ($this->businessId > 0)
            ? AiProvider::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-219::roster-admin', [
            'models' => $models,
            'providers' => $providers,
        ]);
    }
}
