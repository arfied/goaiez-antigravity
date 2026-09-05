<?php

declare(strict_types=1);

namespace App\Modules\CSms;

use App\Modules\CSms\Events\SendRequested;
use App\Modules\CSms\Listeners\SendRequestedListener;
use App\Modules\CSms\Ui\ComposerSegmentWarning;
use App\Modules\CSms\Ui\DonottextList;
use App\Modules\CSms\Ui\PernumberComplaintMonitoring;
use App\Modules\CSms\Ui\Thread;
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
        Event::listen(SendRequested::class, SendRequestedListener::class);
        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'c-sms');

        if (class_exists(Livewire::class)) {
            Livewire::component('c-sms.thread', Thread::class);
            Livewire::component('c-sms.composer-segment-warning', ComposerSegmentWarning::class);
            Livewire::component('c-sms.donottext-list', DonottextList::class);
            Livewire::component('c-sms.pernumber-complaint-monitoring', PernumberComplaintMonitoring::class);
        }
    }
}
