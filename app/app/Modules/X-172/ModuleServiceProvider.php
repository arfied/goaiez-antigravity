<?php

declare(strict_types=1);

namespace App\Modules\X172;

use App\Modules\X172\Ui\CustomerfacingPortal;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-172');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-172.customerfacing-portal', CustomerfacingPortal::class);
        }

        Route::middleware('web')
            ->get('/portal/{token}', CustomerfacingPortal::class)
            ->name('x-172.portal');
    }
}
