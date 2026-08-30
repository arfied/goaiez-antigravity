<?php

declare(strict_types=1);

namespace App\Modules\X120;

use App\Modules\X120\Ui\CardScreen;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-120');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-120.card-screen', CardScreen::class);
        }
    }
}
