<?php

declare(strict_types=1);

namespace App\Modules\CAgent;

use App\Modules\CAgent\Ui\GroundcheckScreen;
use App\Modules\CAgent\Ui\RefusalcodeDistributionPer;
use App\Modules\CAgent\Ui\TeachingBox;
use App\Modules\CAgent\Ui\Thread;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'c-agent');

        if (class_exists(Livewire::class)) {
            Livewire::component('c-agent.thread', Thread::class);
            Livewire::component('c-agent.groundcheck-screen', GroundcheckScreen::class);
            Livewire::component('c-agent.teaching-box', TeachingBox::class);
            Livewire::component('c-agent.refusalcode-distribution-per', RefusalcodeDistributionPer::class);
        }
    }
}
