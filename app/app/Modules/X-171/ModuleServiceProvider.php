<?php

declare(strict_types=1);

namespace App\Modules\X171;

use App\Modules\X171\Ui\StafffacingApp;
use App\Modules\X171\Ui\SyncFailureRate;
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
        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-171');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-171.stafffacing-app', StafffacingApp::class);
            Livewire::component('x-171.sync-failure-rate', SyncFailureRate::class);
        }
    }
}
