<?php

declare(strict_types=1);

namespace App\Modules\X173;

use App\Modules\X173\Ui\ConflictsListView;
use App\Modules\X173\Ui\ConnectionMappingView;
use App\Modules\X173\Ui\SyncErrorRateView;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-173');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-173.connection-mapping', ConnectionMappingView::class);
            Livewire::component('x-173.conflicts-list', ConflictsListView::class);
            Livewire::component('x-173.sync-error-rate', SyncErrorRateView::class);
        }
    }
}
