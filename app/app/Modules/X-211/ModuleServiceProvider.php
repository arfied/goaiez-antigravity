<?php

declare(strict_types=1);

namespace App\Modules\X211;

use App\Modules\X199\Events\InvoiceOverdue;
use App\Modules\X211\Listeners\ChaseOverdueInvoice;
use App\Modules\X211\Ui\AgeingByReason;
use App\Modules\X211\Ui\CollectionsPackagePreview;
use App\Modules\X211\Ui\InvoiceThreadBeside;
use App\Modules\X211\Ui\PaymentplanBuilder;
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
            InvoiceOverdue::class,
            ChaseOverdueInvoice::class
        );

        if (class_exists(Livewire::class)) {
            Livewire::component('x-211.ageing-by-reason', AgeingByReason::class);
            Livewire::component('x-211.invoice-thread-beside', InvoiceThreadBeside::class);
            Livewire::component('x-211.paymentplan-builder', PaymentplanBuilder::class);
            Livewire::component('x-211.collections-package-preview', CollectionsPackagePreview::class);
        }
    }
}
