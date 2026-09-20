<?php

declare(strict_types=1);

namespace App\Modules\CBilling;

use App\Modules\CBilling\Ui\Credits;
use App\Modules\CBilling\Ui\DunningBoard;
use App\Modules\CBilling\Ui\Mrr;
use App\Modules\CBilling\Ui\RevenueRecovery;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'c-billing');

        if (class_exists(Livewire::class)) {
            Livewire::component('c-billing.credits', Credits::class);
            Livewire::component('c-billing.mrr', Mrr::class);
            Livewire::component('c-billing.revenue-recovery', RevenueRecovery::class);
            Livewire::component('c-billing.dunning-board', DunningBoard::class);
        }
    }
}
