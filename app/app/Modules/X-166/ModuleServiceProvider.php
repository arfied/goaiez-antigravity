<?php

declare(strict_types=1);

namespace App\Modules\X166;

use App\Modules\X166\Ui\ByService;
use App\Modules\X166\Ui\BySource;
use App\Modules\X166\Ui\ByTech;
use App\Modules\X166\Ui\MarginByJob;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-166');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-166.margin-by-job', MarginByJob::class);
            Livewire::component('x-166.by-tech', ByTech::class);
            Livewire::component('x-166.by-service', ByService::class);
            Livewire::component('x-166.by-source', BySource::class);
        }
    }
}
