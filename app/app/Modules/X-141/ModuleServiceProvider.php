<?php

declare(strict_types=1);

namespace App\Modules\X141;

use App\Modules\X141\Ui\CounterfactualView;
use App\Modules\X141\Ui\ReplayRuntimeCostView;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-141');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-141.counterfactual-view', CounterfactualView::class);
            Livewire::component('x-141.replay-runtime-cost', ReplayRuntimeCostView::class);
        }
    }
}
