<?php

declare(strict_types=1);

namespace App\Modules\X181;

use App\Modules\X181\Ui\QaQueueSlaDueAt;
use App\Modules\X181\Ui\Resolution;
use App\Modules\X181\Ui\Ticket;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-181');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-181.qa-queue-sladueat', QaQueueSlaDueAt::class);
            Livewire::component('x-181.ticket', Ticket::class);
            Livewire::component('x-181.resolution', Resolution::class);
        }
    }
}
