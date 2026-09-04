<?php

declare(strict_types=1);

namespace App\Modules\X188;

use App\Modules\X188\Ui\ParkList;
use App\Modules\X188\Ui\PernumberComplaintBoard;
use App\Modules\X188\Ui\PoolInventory;
use App\Modules\X188\Ui\YourNumberCard;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-188');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-188.your-number-card', YourNumberCard::class);
            Livewire::component('x-188.pool-inventory', PoolInventory::class);
            Livewire::component('x-188.park-list', ParkList::class);
            Livewire::component('x-188.pernumber-complaint-board', PernumberComplaintBoard::class);
        }
    }
}
