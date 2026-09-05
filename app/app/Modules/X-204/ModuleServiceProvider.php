<?php

declare(strict_types=1);

namespace App\Modules\X204;

use App\Modules\X204\Events\SuppressionAdded;
use App\Modules\X204\Listeners\CancelPendingStepsOnSuppression;
use App\Modules\X204\Ui\RefusalsByReason;
use App\Modules\X204\Ui\RegisterSlotStates;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

require_once __DIR__.'/Listeners/CancelPendingStepsOnSuppression.php';

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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-204');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-204.refusals-by-reason', RefusalsByReason::class);
            Livewire::component('x-204.register-slot-states', RegisterSlotStates::class);
        }

        Event::listen(
            SuppressionAdded::class,
            CancelPendingStepsOnSuppression::class
        );
    }
}
