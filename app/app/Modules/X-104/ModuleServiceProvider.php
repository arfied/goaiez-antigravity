<?php

declare(strict_types=1);

namespace App\Modules\X104;

use App\Modules\X104\Ui\InstallCount;
use App\Modules\X104\Ui\PluginSettingsPage;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-104');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-104.plugin-settings-page', PluginSettingsPage::class);
            Livewire::component('x-104.install-count', InstallCount::class);
        }
    }
}
