<?php

declare(strict_types=1);

namespace App\Modules\X119;

use App\Modules\X119\Actions\FactTeachAction;
use App\Modules\X119\Ui\FactFreshnessPer;
use App\Modules\X119\Ui\PriceConfirmationScreen;
use App\Modules\X119\Ui\ReviewwhatifoundScreen;
use App\Modules\X119\Ui\TeachingBox;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-119');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-119.reviewwhatifound-screen', ReviewwhatifoundScreen::class);
            Livewire::component('x-119.price-confirmation-screen', PriceConfirmationScreen::class);
            Livewire::component('x-119.teaching-box', TeachingBox::class);
            Livewire::component('x-119.fact-freshness-per', FactFreshnessPer::class);
        }

        Event::listen(
            'App\Modules\X163\Events\PricebookUpdated',
            function (object $event) {
                app(FactTeachAction::class)->handle(
                    $event->businessId,
                    'price.'.Str::slug($event->serviceName),
                    (string) $event->priceCents,
                    'pricebook'
                );
            }
        );
    }
}
