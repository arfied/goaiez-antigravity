<?php

declare(strict_types=1);

namespace App\Modules\X104\Ui;

use App\Modules\X104\Models\PluginInstall;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Plugin sites'])]
class PluginSettingsPage extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        return view('x-104::plugin-settings-page', [
            'installs' => ($this->businessId > 0) ? PluginInstall::where('business_id', $this->businessId)->orderByDesc('id')->get() : collect()
        ]);
    }
}
