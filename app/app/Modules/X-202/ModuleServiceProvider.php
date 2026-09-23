<?php

declare(strict_types=1);

namespace App\Modules\X202;

use App\Modules\X103\Events\ApprovalRequested;
use App\Modules\X202\Listeners\EnqueueOnApprovalRequested;
use App\Modules\X202\Ui\AuditExport;
use App\Modules\X202\Ui\Item;
use App\Modules\X202\Ui\Mobile;
use App\Modules\X202\Ui\Queue;
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

        Event::listen(ApprovalRequested::class, EnqueueOnApprovalRequested::class);

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-202');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-202.queue', Queue::class);
            Livewire::component('x-202.item', Item::class);
            Livewire::component('x-202.audit-export', AuditExport::class);
            Livewire::component('x-202.mobile', Mobile::class);
        }
    }
}
