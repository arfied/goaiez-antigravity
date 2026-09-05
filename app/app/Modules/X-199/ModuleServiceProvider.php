<?php

declare(strict_types=1);

namespace App\Modules\X199;

use App\Modules\X199\Console\MarkInvoicesDueCommand;
use App\Modules\X199\Ui\Credits;
use App\Modules\X199\Ui\Declines;
use App\Modules\X199\Ui\Invoices;
use App\Modules\X199\Ui\MoneyPaidToday;
use App\Modules\X199\Ui\Unpaid;
use Illuminate\Console\Scheduling\Schedule;
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
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-199');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-199.money-paid-today', MoneyPaidToday::class);
            Livewire::component('x-199.unpaid', Unpaid::class);
            Livewire::component('x-199.declines', Declines::class);
            Livewire::component('x-199.invoices', Invoices::class);
            Livewire::component('x-199.credits', Credits::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                MarkInvoicesDueCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('x199:mark-due')->daily()->withoutOverlapping(180);
            });
        }
    }
}
