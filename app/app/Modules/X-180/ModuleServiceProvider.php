<?php

declare(strict_types=1);

namespace App\Modules\X180;

use App\Modules\X180\Ui\PackBrowser;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-180');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-180.pack-browser', PackBrowser::class);
        }
    }
}
