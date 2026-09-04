<?php

declare(strict_types=1);

namespace App\Modules\X212;

use App\Modules\X212\Ui\Commit;
use App\Modules\X212\Ui\DryrunPreview;
use App\Modules\X212\Ui\PickSource;
use App\Modules\X212\Ui\PostimportAudit;
use App\Modules\X212\Ui\ReconciliationReport;
use App\Modules\X212\Ui\UnmatchedfieldMap;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-212');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-212.pick-source', PickSource::class);
            Livewire::component('x-212.unmatchedfield-map', UnmatchedfieldMap::class);
            Livewire::component('x-212.dryrun-preview', DryrunPreview::class);
            Livewire::component('x-212.reconciliation-report', ReconciliationReport::class);
            Livewire::component('x-212.commit', Commit::class);
            Livewire::component('x-212.postimport-audit', PostimportAudit::class);
        }
    }
}
