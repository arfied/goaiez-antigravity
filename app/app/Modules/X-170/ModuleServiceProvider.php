<?php

declare(strict_types=1);

namespace App\Modules\X170;

use App\Modules\X170\Ui\Commissions;
use App\Modules\X170\Ui\ScorecardUi;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-170');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-170.commissions', Commissions::class);
            Livewire::component('x-170.scorecard', ScorecardUi::class);
        }
    }
}
