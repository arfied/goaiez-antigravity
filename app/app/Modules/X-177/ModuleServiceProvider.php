<?php

declare(strict_types=1);

namespace App\Modules\X177;

use App\Modules\X177\Ui\GbpCard;
use App\Modules\X177\Ui\SuspensionriskEventsFleetwide;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-177');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-177.gbp-card', GbpCard::class);
            Livewire::component('x-177.suspensionrisk-events-fleetwide', SuspensionriskEventsFleetwide::class);
        }
    }
}
