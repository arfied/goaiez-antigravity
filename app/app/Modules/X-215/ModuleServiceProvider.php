<?php

declare(strict_types=1);

namespace App\Modules\X215;

use App\Modules\X215\Ui\CommentThread;
use App\Modules\X215\Ui\DocumentStatus;
use App\Modules\X215\Ui\SignaturePad;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-215');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-215.document-status', DocumentStatus::class);
            Livewire::component('x-215.signature-pad', SignaturePad::class);
            Livewire::component('x-215.comment-thread', CommentThread::class);
        }
    }
}
