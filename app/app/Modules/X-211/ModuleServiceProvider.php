<?php

declare(strict_types=1);

namespace App\Modules\X211;

use App\Modules\X211\Console\DetectOverdueReceivablesCommand;
use App\Modules\X211\Console\EvidenceRecoveryCommand;
use App\Modules\X211\Console\RuntimeProofCommand;
use App\Modules\X211\Events\ArOverdue;
use App\Modules\X211\Listeners\ProcessOverdueReceivable;
use App\Modules\X211\Ui\AgeingByReason;
use App\Modules\X211\Ui\CollectionsPackagePreview;
use App\Modules\X211\Ui\InvoiceThreadBeside;
use App\Modules\X211\Ui\PaymentplanBuilder;
use Illuminate\Console\Scheduling\Schedule;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-211');

        Event::listen(
            ArOverdue::class,
            ProcessOverdueReceivable::class
        );

        if (class_exists(Livewire::class)) {
            Livewire::component('x-211.ageing-by-reason', AgeingByReason::class);
            Livewire::component('x-211.invoice-thread-beside', InvoiceThreadBeside::class);
            Livewire::component('x-211.paymentplan-builder', PaymentplanBuilder::class);
            Livewire::component('x-211.collections-package-preview', CollectionsPackagePreview::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                DetectOverdueReceivablesCommand::class,
                EvidenceRecoveryCommand::class,
                RuntimeProofCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('x211:detect-overdue')->daily()->withoutOverlapping(180);
            });
        }
    }
}
