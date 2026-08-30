<?php

declare(strict_types=1);

namespace App\Modules\X162;

use App\Modules\X162\Ui\DispatchBoard;
use App\Modules\X162\Ui\Map;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-162');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-162.dispatch-board', DispatchBoard::class);
            Livewire::component('x-162.map', Map::class);
        }
    }
}
