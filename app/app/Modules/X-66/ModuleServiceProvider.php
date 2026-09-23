<?php

declare(strict_types=1);

namespace App\Modules\X66;

use App\Modules\X66\Ui\Calls;
use App\Modules\X66\Ui\LatencyP50p95Per;
use App\Modules\X66\Ui\LivecoachingWhisperPanel;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-66');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-66.calls', Calls::class);
            Livewire::component('x-66.livecoaching-whisper-panel', LivecoachingWhisperPanel::class);
            Livewire::component('x-66.latency-p50p95-per', LatencyP50p95Per::class);
        }
    }
}
