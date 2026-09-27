<?php

declare(strict_types=1);

namespace App\Modules\X104\Ui;

use App\Modules\X104\Actions\PluginActivateAction;
use App\Modules\X104\Actions\PluginDeactivateAction;
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

    public string $siteUrl = '';

    public string $apiKey = '';

    public ?string $error = null;

    public ?string $success = null;

    public ?string $deactivateSuccess = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function activate(PluginActivateAction $action)
    {
        $this->error = null;
        $this->success = null;

        if ($this->siteUrl === '') {
            $this->error = 'Site URL is required.';

            return;
        }

        if ($this->apiKey === '') {
            $this->error = 'API Key is required.';

            return;
        }

        try {
            $action->activate(
                businessId: Tenancy::idOrFail(),
                siteUrl: $this->siteUrl,
                apiKey: $this->apiKey
            );

            $this->success = "Recorded {$this->siteUrl} — the plugin is not installed on it from here.";
            $this->siteUrl = '';
            $this->apiKey = '';
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deactivate(string $siteUrl, PluginDeactivateAction $action): void
    {
        $this->deactivateSuccess = null;

        $install = $action->deactivate(Tenancy::idOrFail(), $siteUrl);

        $this->deactivateSuccess = 'Recorded as off for '.$install->site_url.'. Nothing on your website changed.';
    }

    public function render()
    {
        return view('x-104::plugin-settings-page', [
            'installs' => ($this->businessId > 0) ? PluginInstall::where('business_id', $this->businessId)->orderByDesc('id')->get() : collect(),
        ]);
    }
}
