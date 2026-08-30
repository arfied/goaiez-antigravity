<?php

declare(strict_types=1);

namespace App\Modules\X124;

use App\Modules\X124\Ui\AssistantunsupportedLog;
use App\Modules\X124\Ui\ChatDockEvery;
use App\Modules\X124\Ui\PreviewCard;
use App\Modules\X124\Ui\TodaysRecommendationStrip;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-124');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-124.chat-dock-every', ChatDockEvery::class);
            Livewire::component('x-124.preview-card', PreviewCard::class);
            Livewire::component('x-124.todays-recommendation-strip', TodaysRecommendationStrip::class);
            Livewire::component('x-124.assistantunsupported-log', AssistantunsupportedLog::class);
        }
    }
}
