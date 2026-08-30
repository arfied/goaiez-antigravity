<?php

declare(strict_types=1);

namespace App\Modules\X217;

use App\Modules\X217\Ui\OfferComposer;
use App\Modules\X217\Ui\RecruitPipeline;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-217');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-217.recruit-pipeline', RecruitPipeline::class);
            Livewire::component('x-217.offer-composer', OfferComposer::class);
        }
    }
}
