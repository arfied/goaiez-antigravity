<?php

declare(strict_types=1);

namespace App\Modules\X190;

use App\Modules\X190\Ui\NetworkMap;
use App\Modules\X190\Ui\PoolDepthPer;
use App\Modules\X190\Ui\SlotBoard;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-190');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-190.slot-board', SlotBoard::class);
            Livewire::component('x-190.network-map', NetworkMap::class);
            Livewire::component('x-190.pool-depth-per', PoolDepthPer::class);
        }
    }
}
