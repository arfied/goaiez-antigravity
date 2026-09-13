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

            // Two tenancy checks guard this read, and both are in the application. short_links carries the platform's public_read
            // policy (FOR SELECT USING (true), app/database/migrations/2026_08_11_130139_create_short_links_table.php) and Postgres
            // ORs permissive policies, so row-level security does not restrict a SELECT here. The checks are this business_id clause
            // and the model's TenantScope global scope (BelongsToTenant), which that migration names as the model's safeguard.
            // Measured in SITE-212: with neither, test_g13_24_short_code_is_redeemable_only_under_its_own_business answers 302
            // where it asserts 404. Do not remove either on the ground that row-level security covers it.
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
