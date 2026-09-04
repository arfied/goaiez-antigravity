<?php

declare(strict_types=1);

namespace App\Modules\CTelephony;

use App\Modules\CTelephony\Ui\CarrierRosterHealth;
use App\Modules\CTelephony\Ui\FailoverLog;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'c-telephony');

        if (class_exists(Livewire::class)) {
            Livewire::component('c-telephony.carrier-roster-health', CarrierRosterHealth::class);
            Livewire::component('c-telephony.failover-log', FailoverLog::class);
        }
    }
}
