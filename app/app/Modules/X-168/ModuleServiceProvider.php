<?php

declare(strict_types=1);

namespace App\Modules\X168;

use App\Modules\X168\Listeners\CloseJobWindowOnJobCompleted;
use App\Modules\X168\Listeners\OpenJobWindowOnTechOnSite;
use App\Modules\X168\Ui\ApprovalsView;
use App\Modules\X168\Ui\OwnHoursView;
use App\Modules\X168\Ui\TimesheetsView;
use App\Modules\X171\Events\JobCompleted;
use App\Modules\X171\Events\TechOnSite;
use Illuminate\Support\Facades\Event;
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

        Event::listen(TechOnSite::class, OpenJobWindowOnTechOnSite::class);
        Event::listen(JobCompleted::class, CloseJobWindowOnJobCompleted::class);

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-168');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-168.timesheets', TimesheetsView::class);
            Livewire::component('x-168.approvals', ApprovalsView::class);
            Livewire::component('x-168.own-hours', OwnHoursView::class);
        }
    }
}
