<?php

declare(strict_types=1);

namespace App\Modules\X200;

use App\Modules\X200\Ui\AbandonmentComplaintRates;
use App\Modules\X200\Ui\AgentDesktop;
use App\Modules\X200\Ui\CampaignBoard;
use App\Modules\X200\Ui\CustomerfacingNone;
use App\Modules\X200\Ui\QaScorecardView;
use App\Modules\X200\Ui\Wallboard;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-200');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-200.agent-desktop', AgentDesktop::class);
            Livewire::component('x-200.campaign-board', CampaignBoard::class);
            Livewire::component('x-200.wallboard', Wallboard::class);
            Livewire::component('x-200.qa-scorecard', QaScorecardView::class);
            Livewire::component('x-200.customerfacing-none', CustomerfacingNone::class);
            Livewire::component('x-200.abandonment-complaint-rates', AbandonmentComplaintRates::class);
        }
    }
}
