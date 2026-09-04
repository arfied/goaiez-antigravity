<?php

declare(strict_types=1);

namespace App\Modules\X132;

use App\Modules\X132\Ui\PersonTimelineView;
use App\Modules\X132\Ui\ResolutionRateConfidenceView;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-132');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-132.person-timeline', PersonTimelineView::class);
            Livewire::component('x-132.resolution-rate-confidence', ResolutionRateConfidenceView::class);
        }
    }
}
