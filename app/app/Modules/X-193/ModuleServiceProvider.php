<?php

declare(strict_types=1);

namespace App\Modules\X193;

use App\Modules\X193\Ui\QuiethourHolds;
use App\Modules\X193\Ui\Sendsbyclass;
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
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-193');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-193.sendsbyclass', Sendsbyclass::class);
            Livewire::component('x-193.quiethour-holds', QuiethourHolds::class);
        }
    }
}
