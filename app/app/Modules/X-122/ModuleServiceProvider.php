<?php

declare(strict_types=1);

namespace App\Modules\X122;

use App\Modules\X122\Ui\ActionLog;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-122');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-122.action-log', ActionLog::class);
        }
    }
}
