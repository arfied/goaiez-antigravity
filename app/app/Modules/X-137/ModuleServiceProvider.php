<?php

declare(strict_types=1);

namespace App\Modules\X137;

use App\Modules\X137\Events\LinkClicked;
use App\Modules\X137\Models\LinkClick;
use App\Modules\X137\Models\ShortLink;
use App\Modules\X137\Ui\AttributionRow;
use App\Modules\X137\Ui\DniPoolUtilisation;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
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

        Route::get('/l/{business}/{code}', function (string $business, string $code, Request $request) {
            $businessId = (int) $business;
            Tenancy::set($businessId);

            $shortLink = ShortLink::where('business_id', $businessId)
                ->where('short_code', $code)
                ->firstOrFail();

            // A link whose destination is blank cannot be followed, so it is not a click: refuse it with the
            // 404 this route already answers for a link that does not resolve, before anything is recorded.
            abort_if(trim((string) $shortLink->destination_url) === '', 404);

            LinkClick::create([
                'business_id' => $businessId,
                'short_link_id' => $shortLink->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'clicked_at' => now(),
            ]);

            Event::dispatch(new LinkClicked(
                businessId: $businessId,
                shortLinkId: $shortLink->id,
                shortCode: $code,
            ));

            return redirect()->away($shortLink->destination_url);
        })->whereNumber('business');
    }
}
