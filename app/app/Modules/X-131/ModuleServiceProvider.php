<?php

declare(strict_types=1);

namespace App\Modules\X131;

use App\Modules\X131\Ui\InterestTagsView;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-131');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-131.interest-tags', InterestTagsView::class);
        }
    }
}
