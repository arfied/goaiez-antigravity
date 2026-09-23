<?php

declare(strict_types=1);

namespace App\Modules\X136;

use App\Events\Voice\CallMissed;
use App\Modules\X01\Events\ContactCreated;
use App\Modules\X136\Listeners\RecordContactSignalListener;
use App\Modules\X136\Listeners\RecordFormSignalListener;
use App\Modules\X136\Listeners\RecordMissedCallSignalListener;
use App\Modules\X136\Ui\CoolingView;
use App\Modules\X136\Ui\SignalVolumePrecisionView;
use App\Modules\X155\Events\FormCaptured;
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

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-136');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-136.cooling', CoolingView::class);
            Livewire::component('x-136.signal-volume-precision', SignalVolumePrecisionView::class);
        }

        Event::listen(ContactCreated::class, RecordContactSignalListener::class);
        Event::listen(FormCaptured::class, RecordFormSignalListener::class);
        Event::listen(CallMissed::class, RecordMissedCallSignalListener::class);
    }
}
