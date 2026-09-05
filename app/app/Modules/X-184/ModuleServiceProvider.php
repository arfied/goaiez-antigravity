<?php

declare(strict_types=1);

namespace App\Modules\X184;

use App\Modules\X184\Ui\CalendarView;
use App\Modules\X184\Ui\ContentWeek;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-184');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-184.content-week', ContentWeek::class);
            Livewire::component('x-184.calendar', CalendarView::class);
        }
    }
}
