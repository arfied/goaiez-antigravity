<?php

declare(strict_types=1);

namespace App\Modules\X160;

use App\Modules\X160\Ui\ExtractionErrorRate;
use App\Modules\X160\Ui\ReviewScreen;
use App\Modules\X160\Ui\UploadDrop;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-160');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-160.upload-drop', UploadDrop::class);
            Livewire::component('x-160.review-screen', ReviewScreen::class);
            Livewire::component('x-160.extraction-error-rate', ExtractionErrorRate::class);
        }
    }
}
