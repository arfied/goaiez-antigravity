<?php

declare(strict_types=1);

namespace App\Modules\X108;

use App\Modules\X108\Ui\Calendar;
use App\Modules\X108\Ui\Waitlist;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-108');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-108.calendar', Calendar::class);
            Livewire::component('x-108.waitlist', Waitlist::class);
        }
    }
}
