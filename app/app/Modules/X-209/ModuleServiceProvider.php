<?php

declare(strict_types=1);

namespace App\Modules\X209;

use App\Modules\X209\Ui\LaddersOwnState;
use App\Modules\X209\Ui\OnetapApprovalCard;
use App\Modules\X209\Ui\PrivateInbox;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-209');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-209.private-inbox', PrivateInbox::class);
            Livewire::component('x-209.onetap-approval-card', OnetapApprovalCard::class);
            Livewire::component('x-209.ladders-own-state', LaddersOwnState::class);
        }
    }
}
