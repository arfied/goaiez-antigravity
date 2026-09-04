<?php

declare(strict_types=1);

namespace App\Modules\X142;

use App\Modules\X142\Ui\ConnectYourAi;
use App\Modules\X142\Ui\McpTokenRegistry;
use App\Modules\X142\Ui\WebhooksView;
use Illuminate\Support\Facades\Route;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-142');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-142.connect-your-ai', ConnectYourAi::class);
            Livewire::component('x-142.webhooks', WebhooksView::class);
            Livewire::component('x-142.mcp-token-registry', McpTokenRegistry::class);
        }

        $this->app->booted(function () {
            Route::middleware(['web', 'auth'])->group(function () {
                Route::get('/x-142/connect-your-ai', ConnectYourAi::class)->name('x-142.connect-your-ai');
                Route::get('/x-142/webhooks', WebhooksView::class)->name('x-142.webhooks');
                Route::get('/x-142/mcp-token-registry', McpTokenRegistry::class)->name('x-142.mcp-token-registry');
            });
        });
    }
}
