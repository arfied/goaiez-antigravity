<?php

declare(strict_types=1);

namespace App\Modules\X121;

use App\Modules\X121\Ui\EntityHistoryViewer;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-121');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-121.entity-history-viewer', EntityHistoryViewer::class);
        }
    }
}
