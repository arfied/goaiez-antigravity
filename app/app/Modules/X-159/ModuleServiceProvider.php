<?php

declare(strict_types=1);

namespace App\Modules\X159;

use App\Modules\X159\Ui\AuditQueue;
use App\Modules\X159\Ui\AuditScore;
use App\Modules\X159\Ui\CustomerprospectfacingAuditPage;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-159');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-159.customerprospectfacing-audit-page', CustomerprospectfacingAuditPage::class);
            Livewire::component('x-159.score', AuditScore::class);
            Livewire::component('x-159.audit-queue', AuditQueue::class);
        }
    }
}
