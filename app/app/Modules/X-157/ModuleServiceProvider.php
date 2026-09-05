<?php

declare(strict_types=1);

namespace App\Modules\X157;

use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Ui\EdgeStatusPer;
use Illuminate\Support\Facades\DB;
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
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-157');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-157.edge-status-per', EdgeStatusPer::class);
        }

        Route::get('/sites/{business}/{deploy_hash}', function (string $business, string $deployHash) {
            $business = (int) $business;
            DB::statement("SELECT set_config('app.business_id', ?, false)", [(string) $business]);

            $deployment = Deployment::where('business_id', $business)->where('deploy_hash', $deployHash)->firstOrFail();
            abort_if($deployment->status !== 'deployed', 404);

            $zone = $deployment->edgeZone;
            abort_if($zone === null || ! $zone->has_valid_ssl, 404);

            $html = Storage::disk('local')->get("sites/{$deployHash}.html");
            abort_if($html === null, 404);

            return response($html, 200)->header('Content-Type', 'text/html');
        })->whereNumber('business');
    }
}
