<?php

declare(strict_types=1);

namespace App\Modules\X201;

use App\Modules\X201\Ui\DisputeCard;
use App\Modules\X201\Ui\DisputeQueue;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-201');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-201.dispute-card', DisputeCard::class);
            Livewire::component('x-201.dispute-queue', DisputeQueue::class);
        }
    }
}
