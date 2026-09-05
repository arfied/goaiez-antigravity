<?php

declare(strict_types=1);

namespace App\Modules\X158;

use App\Modules\X158\Ui\ContentPlansVideo;
use App\Modules\X158\Ui\ProspectfacingVideoDemo;
use App\Modules\X158\Ui\RenderQueue;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-158');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-158.prospectfacing-video-demo', ProspectfacingVideoDemo::class);
            Livewire::component('x-158.content-plans-video', ContentPlansVideo::class);
            Livewire::component('x-158.render-queue', RenderQueue::class);
        }
    }
}
