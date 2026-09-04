<?php

declare(strict_types=1);

namespace App\Modules\X109;

use App\Modules\X109\Ui\ManualQueue;
use App\Modules\X109\Ui\SubmissionLog;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-109');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-109.submission-log', SubmissionLog::class);
            Livewire::component('x-109.manual-queue', ManualQueue::class);
        }
    }
}
