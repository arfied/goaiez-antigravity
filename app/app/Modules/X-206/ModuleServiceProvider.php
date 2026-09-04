<?php

declare(strict_types=1);

namespace App\Modules\X206;

use App\Modules\X206\Ui\Connections;
use App\Modules\X206\Ui\Reveal;
use App\Modules\X206\Ui\RevealLog;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-206');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-206.connections', Connections::class);
            Livewire::component('x-206.reveal', Reveal::class);
            Livewire::component('x-206.reveal-log', RevealLog::class);
        }
    }
}
