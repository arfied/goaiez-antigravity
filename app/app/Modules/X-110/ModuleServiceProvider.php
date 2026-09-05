<?php

declare(strict_types=1);

namespace App\Modules\X110;

use App\Modules\X110\Ui\AbandonedForms;
use App\Modules\X110\Ui\Cooling;
use App\Modules\X110\Ui\InstallVerify;
use App\Modules\X110\Ui\TagVersionPer;
use App\Modules\X110\Ui\Today;
use App\Modules\X110\Ui\VisitorsLive;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-110');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-110.visitors-live', VisitorsLive::class);
            Livewire::component('x-110.today', Today::class);
            Livewire::component('x-110.cooling', Cooling::class);
            Livewire::component('x-110.abandoned-forms', AbandonedForms::class);
            Livewire::component('x-110.install-verify', InstallVerify::class);
            Livewire::component('x-110.tag-version-per', TagVersionPer::class);
        }
    }
}
