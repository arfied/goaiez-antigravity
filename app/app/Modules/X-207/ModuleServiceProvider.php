<?php

declare(strict_types=1);

namespace App\Modules\X207;

use App\Modules\X207\Ui\OneConfirmonceToggle;
use App\Modules\X207\Ui\PerplatformDeliveryHealth;
use App\Modules\X207\Ui\PromptcopyEditor;
use App\Modules\X207\Ui\RetirementReasons;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-207');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-207.one-confirmonce-toggle', OneConfirmonceToggle::class);
            Livewire::component('x-207.promptcopy-editor', PromptcopyEditor::class);
            Livewire::component('x-207.retirement-reasons', RetirementReasons::class);
            Livewire::component('x-207.perplatform-delivery-health', PerplatformDeliveryHealth::class);
        }
    }
}
