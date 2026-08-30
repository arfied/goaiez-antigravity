<?php

declare(strict_types=1);

namespace App\Modules\X219;

use App\Modules\X219\Ui\AssignmentMatrix;
use App\Modules\X219\Ui\RosterAdmin;
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
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-219');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-219.roster-admin', RosterAdmin::class);
            Livewire::component('x-219.assignment-matrix', AssignmentMatrix::class);
        }
    }
}
