<?php

declare(strict_types=1);

namespace App\Modules\X144;

use App\Modules\X144\Ui\QuestionList;
use App\Modules\X144\Ui\TenantZerosOwn;
use App\Modules\X144\Ui\VisibilityTile;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-144');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-144.visibility-tile', VisibilityTile::class);
            Livewire::component('x-144.question-list', QuestionList::class);
            Livewire::component('x-144.tenant-zeros-own', TenantZerosOwn::class);
        }
    }
}
