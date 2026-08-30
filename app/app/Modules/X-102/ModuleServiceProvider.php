<?php

declare(strict_types=1);

namespace App\Modules\X102;

use App\Modules\X102\Ui\CustomerfacingWidget;
use App\Modules\X102\Ui\OfflineFormInbox;
use App\Modules\X102\Ui\RageclickRate;
use App\Modules\X102\Ui\Thread;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-102');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-102.customerfacing-widget', CustomerfacingWidget::class);
            Livewire::component('x-102.thread', Thread::class);
            Livewire::component('x-102.offline-form-inbox', OfflineFormInbox::class);
            Livewire::component('x-102.rageclick-rate', RageclickRate::class);
        }
    }
}
