<?php

declare(strict_types=1);

namespace App\Modules\X214;

use App\Modules\X214\Ui\SurchargeDisclosure;
use App\Modules\X214\Ui\SurchargeLine;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-214');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-214.surcharge-line', SurchargeLine::class);
            Livewire::component('x-214.surcharge-disclosure', SurchargeDisclosure::class);
        }
    }
}
