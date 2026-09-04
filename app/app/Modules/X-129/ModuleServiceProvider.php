<?php

declare(strict_types=1);

namespace App\Modules\X129;

use App\Modules\X129\Ui\CutoverQueue;
use App\Modules\X129\Ui\MigrationCard;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-129');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-129.migration-card', MigrationCard::class);
            Livewire::component('x-129.cutover-queue', CutoverQueue::class);
        }
    }
}
