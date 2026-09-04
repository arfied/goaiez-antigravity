<?php

declare(strict_types=1);

namespace App\Modules\X139;

use App\Modules\X139\Ui\AdaccountConnectCard;
use App\Modules\X139\Ui\ConversionsPushedTile;
use App\Modules\X139\Ui\RejectionRate;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-139');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-139.adaccount-connect-card', AdaccountConnectCard::class);
            Livewire::component('x-139.conversions-pushed-tile', ConversionsPushedTile::class);
            Livewire::component('x-139.rejection-rate', RejectionRate::class);
        }
    }
}
