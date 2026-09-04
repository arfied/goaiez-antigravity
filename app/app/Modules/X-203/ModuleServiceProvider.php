<?php

declare(strict_types=1);

namespace App\Modules\X203;

use App\Modules\X203\Ui\DrDashboard;
use App\Modules\X203\Ui\RestorationtestLog;
use App\Modules\X203\Ui\RunbookRunner;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-203');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-203.dr-dashboard', DrDashboard::class);
            Livewire::component('x-203.restorationtest-log', RestorationtestLog::class);
            Livewire::component('x-203.runbook-runner', RunbookRunner::class);
        }
    }
}
