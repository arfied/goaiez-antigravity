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
        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-137');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-137.attribution-row', AttributionRow::class);
            Livewire::component('x-137.dni-pool-utilisation', DniPoolUtilisation::class);
        }

        \Illuminate\Support\Facades\Route::get('/l/{business}/{code}', function (string $business, string $code, \Illuminate\Http\Request $request) {
            $businessId = (int) $business;
            \App\Support\Tenancy::set($businessId);

            $shortLink = \App\Modules\X137\Models\ShortLink::where('business_id', $businessId)
                ->where('short_code', $code)
                ->firstOrFail();

            \App\Modules\X137\Models\LinkClick::create([
                'business_id' => $businessId,
                'short_link_id' => $shortLink->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'clicked_at' => now(),
            ]);

            \Illuminate\Support\Facades\Event::dispatch(new \App\Modules\X137\Events\LinkClicked(
                businessId: $businessId,
                shortLinkId: $shortLink->id,
                shortCode: $code,
            ));

            return redirect()->away($shortLink->destination_url);
        })->whereNumber('business');
    }
}
