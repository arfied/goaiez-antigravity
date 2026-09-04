<?php

declare(strict_types=1);

namespace App\Modules\X140;

use App\Modules\X140\Ui\ProposedPagesView;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-140');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-140.proposed-pages', ProposedPagesView::class);
        }
    }
}
