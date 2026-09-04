<?php

declare(strict_types=1);

namespace App\Modules\X208;

use App\Modules\X208\Ui\Cost;
use App\Modules\X208\Ui\PiecePreview;
use App\Modules\X208\Ui\SendRecord;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-208');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-208.piece-preview', PiecePreview::class);
            Livewire::component('x-208.cost', Cost::class);
            Livewire::component('x-208.send-record', SendRecord::class);
        }
    }
}
