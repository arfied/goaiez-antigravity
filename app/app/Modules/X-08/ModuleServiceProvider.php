<?php

declare(strict_types=1);

namespace App\Modules\X08;

use App\Modules\X08\Ui\ReasonPerRowView;
use App\Modules\X08\Ui\RiskListView;
use App\Modules\X08\Ui\SortedView;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-08');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-08.risk-list', RiskListView::class);
            Livewire::component('x-08.sorted', SortedView::class);
            Livewire::component('x-08.reason-per-row', ReasonPerRowView::class);
        }
    }
}
