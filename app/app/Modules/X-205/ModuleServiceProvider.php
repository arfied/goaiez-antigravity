<?php

declare(strict_types=1);

namespace App\Modules\X205;

use App\Modules\X205\Ui\AffiliatePortal;
use App\Modules\X205\Ui\EarningsView;
use App\Modules\X205\Ui\PayoutRunView;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-205');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-205.portal', AffiliatePortal::class);
            Livewire::component('x-205.earnings', EarningsView::class);
            Livewire::component('x-205.payout-run', PayoutRunView::class);
        }
    }
}
