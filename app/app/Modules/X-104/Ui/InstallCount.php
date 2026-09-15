<?php

declare(strict_types=1);

namespace App\Modules\X104\Ui;

use App\Modules\X104\Models\PluginInstall;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Plugin installs'])]
class InstallCount extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $count = ($this->businessId > 0)
            ? PluginInstall::where('business_id', $this->businessId)->where('is_active', true)->count()
            : 0;

        return view('x-104::install-count', [
            'count' => $count,
        ]);
    }
}
