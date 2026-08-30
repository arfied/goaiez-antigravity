<?php

declare(strict_types=1);

namespace App\Modules\X179;

use App\Modules\X179\Ui\MatchScores;
use App\Modules\X179\Ui\ProspecttenantfacingTop3Preview;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-179');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-179.prospecttenantfacing-top3-preview', ProspecttenantfacingTop3Preview::class);
            Livewire::component('x-179.match-scores', MatchScores::class);
        }
    }
}
