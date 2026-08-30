<?php

declare(strict_types=1);

namespace App\Modules\X114;

use App\Modules\X114\Ui\BrandKitView;
use App\Modules\X114\Ui\MediaLibraryView;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-114');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-114.brand-kit', BrandKitView::class);
            Livewire::component('x-114.media-library', MediaLibraryView::class);
        }
    }
}
