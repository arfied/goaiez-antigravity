<?php

declare(strict_types=1);

namespace App\Modules\X10;

use App\Modules\X01\Events\ContactCreated;
use App\Modules\X10\Listeners\AssignNewContactListener;
use App\Modules\X10\Ui\RoutingRules;
use App\Modules\X10\Ui\TerritoryMap;
use App\Modules\X10\Ui\UnassignedCount;
use Illuminate\Support\Facades\Event;
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

        Event::listen(
            ContactCreated::class,
            AssignNewContactListener::class
        );

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-10');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-10.routing-rules', RoutingRules::class);
            Livewire::component('x-10.territory-map', TerritoryMap::class);
            Livewire::component('x-10.unassigned-count', UnassignedCount::class);
        }
    }
}
