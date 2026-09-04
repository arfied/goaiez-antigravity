<?php

declare(strict_types=1);

namespace App\Modules\X119;

use App\Modules\X119\Ui\FactFreshnessPer;
use App\Modules\X119\Ui\PriceConfirmationScreen;
use App\Modules\X119\Ui\ReviewwhatifoundScreen;
use App\Modules\X119\Ui\TeachingBox;
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






        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-119');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-119.reviewwhatifound-screen', ReviewwhatifoundScreen::class);
            Livewire::component('x-119.price-confirmation-screen', PriceConfirmationScreen::class);
            Livewire::component('x-119.teaching-box', TeachingBox::class);
            Livewire::component('x-119.fact-freshness-per', FactFreshnessPer::class);
        }
    }
}
