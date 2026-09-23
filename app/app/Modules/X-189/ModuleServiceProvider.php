<?php

declare(strict_types=1);

namespace App\Modules\X189;

use App\Modules\X189\Ui\BrandCardEditor;
use App\Modules\X189\Ui\PreviewPerDestination;
use App\Modules\X189\Ui\X189MediaController;
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

        Route::get('/m/x189/{business}/{branded}', X189MediaController::class)
            ->middleware(['signed', 'throttle:feedback-view'])
            ->whereNumber(['business', 'branded'])
            ->name('x-189.media');

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-189');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-189.brand-card-editor', BrandCardEditor::class);
            Livewire::component('x-189.preview-per-destination', PreviewPerDestination::class);
        }
    }
}
