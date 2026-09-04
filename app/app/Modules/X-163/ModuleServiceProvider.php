<?php

declare(strict_types=1);

namespace App\Modules\X163;

use App\Modules\X163\Ui\ConfirmationScreen;
use App\Modules\X163\Ui\DailyPricingDigest;
use App\Modules\X163\Ui\Pricebook;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-163');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-163.pricebook', Pricebook::class);
            Livewire::component('x-163.confirmation-screen', ConfirmationScreen::class);
            Livewire::component('x-163.daily-pricing-digest', DailyPricingDigest::class);
        }
    }
}
