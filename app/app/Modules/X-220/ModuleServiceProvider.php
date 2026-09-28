<?php

declare(strict_types=1);

namespace App\Modules\X220;

use App\Events\ReplyApproved;
use App\Modules\X220\Listeners\GoldenCaseFromApprovalListener;
use App\Modules\X220\Ui\EvalReport;
use App\Modules\X220\Ui\PromptHistory;
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
        $this->app['events']->listen(ReplyApproved::class, GoldenCaseFromApprovalListener::class);
        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-220');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-220.prompt-history', PromptHistory::class);
            Livewire::component('x-220.eval-report', EvalReport::class);
        }
    }
}
