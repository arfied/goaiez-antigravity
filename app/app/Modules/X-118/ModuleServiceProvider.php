<?php

declare(strict_types=1);

namespace App\Modules\X118;

use App\Modules\X118\Ui\DayOneSignup;
use App\Modules\X118\Ui\Groundcheck;
use App\Modules\X118\Ui\SameFlow;
use App\Modules\X118\Ui\TestCall;
use App\Modules\X118\Ui\Today;
use App\Modules\X118\Ui\TtfmDistribution;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-118');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-118.day-one-signup', DayOneSignup::class);
            Livewire::component('x-118.groundcheck', Groundcheck::class);
            Livewire::component('x-118.test-call', TestCall::class);
            Livewire::component('x-118.today', Today::class);
            Livewire::component('x-118.same-flow', SameFlow::class);
            Livewire::component('x-118.ttfm-distribution', TtfmDistribution::class);
        }
    }
}
