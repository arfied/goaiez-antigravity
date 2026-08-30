<?php

declare(strict_types=1);

namespace App\Modules\X191;

use App\Modules\X191\Ui\LinksEarned;
use App\Modules\X191\Ui\PitchacquireRatio;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-191');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-191.links-earned', LinksEarned::class);
            Livewire::component('x-191.pitchacquire-ratio', PitchacquireRatio::class);
        }
    }
}
