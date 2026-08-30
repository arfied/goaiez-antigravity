<?php

declare(strict_types=1);

namespace App\Modules\X16;

use App\Modules\X16\Ui\GeogridMap;
use App\Modules\X16\Ui\HarvestCoverageBy;
use App\Modules\X16\Ui\ServiceareaPolygon;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-16');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-16.geogrid-map', GeogridMap::class);
            Livewire::component('x-16.servicearea-polygon', ServiceareaPolygon::class);
            Livewire::component('x-16.harvest-coverage-by', HarvestCoverageBy::class);
        }
    }
}
