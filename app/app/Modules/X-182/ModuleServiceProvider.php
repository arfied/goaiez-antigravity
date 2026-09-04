<?php

declare(strict_types=1);

namespace App\Modules\X182;

use App\Modules\X182\Ui\ConnectedAccounts;
use App\Modules\X182\Ui\SocialQueue;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-182');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-182.connected-accounts', ConnectedAccounts::class);
            Livewire::component('x-182.social-queue', SocialQueue::class);
        }
    }
}
