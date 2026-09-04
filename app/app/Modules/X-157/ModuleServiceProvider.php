<?php

declare(strict_types=1);

namespace App\Modules\X157;

use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Ui\EdgeStatusPer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-157');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-157.edge-status-per', EdgeStatusPer::class);
        }

        Route::get('/sites/{deploy_hash}', function (string $deployHash) {
            $deployment = Deployment::where('deploy_hash', $deployHash)->firstOrFail();

            $zone = $deployment->edgeZone;
            abort_if($zone === null || ! $zone->has_valid_ssl, 404);

            $html = Storage::disk('local')->get("sites/{$deployHash}.html");

            return response($html, 200)->header('Content-Type', 'text/html');
        });
    }
}
