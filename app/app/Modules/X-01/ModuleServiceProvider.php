<?php

declare(strict_types=1);

namespace App\Modules\X01;

use App\Modules\X01\Ui\Account\Inbox as AccountInbox;
use App\Modules\X01\Ui\CustomersList;
use App\Modules\X01\Ui\History;
use App\Modules\X01\Ui\PaymentRisk;
use App\Modules\X01\Ui\Person as PersonComponent;
use App\Modules\X01\Ui\Thread;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-01');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-01.thread', Thread::class);
            Livewire::component('x-01.customers-list', CustomersList::class);
            Livewire::component('x-01.person', PersonComponent::class);
            Livewire::component('x-01.history', History::class);
            Livewire::component('x-01.payment-risk', PaymentRisk::class);
            Livewire::component('x-01.account.inbox', AccountInbox::class);
        }
    }
}
