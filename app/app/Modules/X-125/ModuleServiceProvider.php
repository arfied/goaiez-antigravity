<?php

declare(strict_types=1);

namespace App\Modules\X125;

use App\Modules\X125\Ui\Canvas;
use App\Modules\X125\Ui\FlowErrorDashboard;
use App\Modules\X125\Ui\Runs;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-125');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-125.canvas', Canvas::class);
            Livewire::component('x-125.runs', Runs::class);
            Livewire::component('x-125.flow-error-dashboard', FlowErrorDashboard::class);
        }
    }
}
