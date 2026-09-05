<?php

declare(strict_types=1);

namespace App\Modules\X218;

use App\Modules\X218\Ui\DealTracker;
use App\Modules\X218\Ui\DeliverableProof;
use App\Modules\X218\Ui\DiscoveryBoard;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-218');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-218.discovery-board', DiscoveryBoard::class);
            Livewire::component('x-218.deal-tracker', DealTracker::class);
            Livewire::component('x-218.deliverable-proof', DeliverableProof::class);
        }
    }
}
