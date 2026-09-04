<?php

declare(strict_types=1);

namespace App\Modules\X167;

use App\Modules\X167\Ui\Reorders;
use App\Modules\X167\Ui\StockByVan;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-167');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-167.stock-by-van', StockByVan::class);
            Livewire::component('x-167.reorders', Reorders::class);
        }
    }
}
