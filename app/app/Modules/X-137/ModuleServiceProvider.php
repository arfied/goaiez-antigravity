<?php

declare(strict_types=1);

namespace App\Modules\X137;

use App\Modules\X137\Ui\AttributionRow;
use App\Modules\X137\Ui\DniPoolUtilisation;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-137');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-137.attribution-row', AttributionRow::class);
            Livewire::component('x-137.dni-pool-utilisation', DniPoolUtilisation::class);
        }

        \Illuminate\Support\Facades\Route::get('/l/{business}/{code}', function (string $business, string $code, \Illuminate\Http\Request $request) {
            $business = (int) $business;
            \Illuminate\Support\Facades\DB::statement("SELECT set_config('app.business_id', ?, false)", [(string) $business]);

            return app(\App\Modules\X137\Actions\LinkRedirectAction::class)->handle(
                $business, 
                $code, 
                $request->ip(), 
                $request->userAgent()
            );
        })->whereNumber('business');
    }
}
