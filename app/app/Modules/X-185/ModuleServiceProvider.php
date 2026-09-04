<?php

declare(strict_types=1);

namespace App\Modules\X185;

use App\Modules\X185\Ui\DigestLine;
use App\Modules\X185\Ui\ExperimentBoard;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-185');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-185.digest-line', DigestLine::class);
            Livewire::component('x-185.experiment-board', ExperimentBoard::class);
        }
    }
}
