<?php

declare(strict_types=1);

namespace App\Modules\X198;

use App\Modules\X117\Events\CartCheckedOut;
use App\Modules\X198\Listeners\CaptureCheckedOutCart;
use App\Modules\X198\Ui\ConnectCard;
use App\Modules\X198\Ui\ReconciliationDiscrepancies;
use App\Modules\X198\Ui\SameAccount;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-198');

        Event::listen(CartCheckedOut::class, [CaptureCheckedOutCart::class, 'handle']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\EvidenceChargeCommand::class,
                Console\RuntimeProofCommand::class,
                Console\EvidencePaymentLinkCommand::class,
            ]);
        }

        if (class_exists(Livewire::class)) {
            Livewire::component('x-198.connect-card', ConnectCard::class);
            Livewire::component('x-198.same-account', SameAccount::class);
            Livewire::component('x-198.reconciliation-discrepancies', ReconciliationDiscrepancies::class);
        }
    }
}
