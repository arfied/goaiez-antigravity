<?php

declare(strict_types=1);

namespace App\Modules\X210;

use App\Modules\X210\Ui\ActivePromotions;
use App\Modules\X210\Ui\EarnedVsGivenPanel;
use App\Modules\X210\Ui\PromotionBuilder;
use App\Modules\X210\Ui\RedemptionsList;
use App\Modules\X210\Ui\TargetingPreview;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-210');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-210.promotion-builder', PromotionBuilder::class);
            Livewire::component('x-210.active-promotions', ActivePromotions::class);
            Livewire::component('x-210.redemptions', RedemptionsList::class);
            Livewire::component('x-210.earnedvsgiven-panel', EarnedVsGivenPanel::class);
            Livewire::component('x-210.targeting-preview', TargetingPreview::class);
        }
    }
}
