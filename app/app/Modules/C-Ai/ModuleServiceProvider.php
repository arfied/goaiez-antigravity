<?php

declare(strict_types=1);

namespace App\Modules\CAi;

use App\Modules\CAgent\Events\AgentTurnStarted;
use App\Modules\CAi\Listeners\RecordAiCallOnAgentTurnStarted;
use App\Modules\CAi\Ui\ModelBoard;
use Illuminate\Support\Facades\Event;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'c-ai');

        if (class_exists(Livewire::class)) {
            Livewire::component('c-ai.model-board', ModelBoard::class);
        }

        Event::listen(
            AgentTurnStarted::class,
            RecordAiCallOnAgentTurnStarted::class
        );
    }
}
