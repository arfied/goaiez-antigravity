<?php

declare(strict_types=1);

namespace App\Modules\X183;

use App\Modules\X183\Ui\DraftReview;
use App\Modules\X183\Ui\GateRejectionReasons;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-183');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-183.draft-review', DraftReview::class);
            Livewire::component('x-183.gate-rejection-reasons', GateRejectionReasons::class);
        }
    }
}
