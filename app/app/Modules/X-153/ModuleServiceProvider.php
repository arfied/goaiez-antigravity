<?php

declare(strict_types=1);

namespace App\Modules\X153;

use App\Modules\X153\Ui\AlertReplyBy;
use App\Modules\X153\Ui\AlertRosterScreen;
use App\Modules\X153\Ui\ClaimexpiryRate;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-153');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-153.alert-reply-by', AlertReplyBy::class);
            Livewire::component('x-153.alert-roster-screen', AlertRosterScreen::class);
            Livewire::component('x-153.claimexpiry-rate', ClaimexpiryRate::class);
        }
    }
}
