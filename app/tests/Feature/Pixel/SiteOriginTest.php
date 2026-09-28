<?php

declare(strict_types=1);

use App\Enums\PixelCollectionState;
use App\Jobs\ArchivePixelBatchJob;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use App\Services\Pixel\PixelCollections;
use App\Services\Widgets\WidgetPlugins;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

it('a page view posted from the platform host is archived once the business has a site there', function () {
    $platform = 'https://'.(string) parse_url((string) config('app.url'), PHP_URL_HOST);

    Bus::fake();
    $biz = pixelTenant('Post 4953', 'post-4953.test');
    Tenancy::set((int) $biz->id);

    postPixel(pixelBody(pixelKeyFor($biz)), 'https://post-4953.test')->assertNoContent();
    Bus::assertDispatched(ArchivePixelBatchJob::class);

    Bus::fake();
    postPixel(pixelBody(pixelKeyFor($biz)), $platform)->assertNoContent();
    Bus::assertNotDispatched(ArchivePixelBatchJob::class);

    app(PlatformSiteAddressAction::class)->handle((int) $biz->id);

    Bus::fake();
    postPixel(pixelBody(pixelKeyFor($biz)), $platform)->assertNoContent();
    Bus::assertDispatched(ArchivePixelBatchJob::class);
});

it('refuses the platform host for a business with no site there', function () {
    $platform = 'https://'.(string) parse_url((string) config('app.url'), PHP_URL_HOST);

    $biz = pixelTenant('Bare 4948', 'bare-4948.test');
    Tenancy::set((int) $biz->id);

    $plugins = app(WidgetPlugins::class);
    expect($plugins->businessAllowsOrigin($platform))->toBeFalse();
    expect($plugins->acceptedHosts())->toBe(['bare-4948.test']);
});

it('accepts the platform host once the business has a site at the platform address', function () {
    $platform = 'https://'.(string) parse_url((string) config('app.url'), PHP_URL_HOST);
    $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

    $biz = pixelTenant('Bare 4948', 'bare-4948.test');
    Tenancy::set((int) $biz->id);

    app(PlatformSiteAddressAction::class)->handle((int) $biz->id);

    $plugins = app(WidgetPlugins::class);
    expect($plugins->businessAllowsOrigin($platform))->toBeTrue();
    expect($plugins->acceptedHosts())->toContain($host, 'bare-4948.test');
});

it('accepts a verified custom domain and refuses an unverified one', function () {
    $biz = pixelTenant('Roof 4949', 'roof-4949.test');
    Tenancy::set((int) $biz->id);

    DB::table('custom_domain_requests')->insert([
        [
            'business_id' => $biz->id,
            'domain' => 'roof-4949.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'business_id' => $biz->id,
            'domain' => 'roof-4950.test',
            'status' => 'unverified',
            'verified_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $plugins = app(WidgetPlugins::class);
    expect($plugins->businessAllowsOrigin('https://roof-4949.test'))->toBeTrue();
    expect($plugins->businessAllowsOrigin('https://roof-4950.test'))->toBeFalse();
});

it('another tenant\'s platform site does not open the platform host for this tenant', function () {
    $platform = 'https://'.(string) parse_url((string) config('app.url'), PHP_URL_HOST);

    $biz1 = pixelTenant('Site 4951', 'site-4951.test');
    Tenancy::set((int) $biz1->id);
    app(PlatformSiteAddressAction::class)->handle((int) $biz1->id);

    $biz2 = pixelTenant('Bare 4952', 'bare-4952.test');
    Tenancy::set((int) $biz2->id);

    $plugins = app(WidgetPlugins::class);
    expect($plugins->businessAllowsOrigin($platform))->toBeFalse();
});

it('the pixel screen lists the published site host', function () {
    $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

    $biz = pixelTenant('List 4953', 'list-4953.test');
    Tenancy::set((int) $biz->id);

    app(PlatformSiteAddressAction::class)->handle((int) $biz->id);

    $collections = app(PixelCollections::class);
    $status = $collections->status();

    expect($status->listedSites)->toContain($host);
    expect($status->state)->not->toBe(PixelCollectionState::NoWebsiteListed);
});
