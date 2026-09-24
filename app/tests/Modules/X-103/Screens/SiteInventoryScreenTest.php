<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Exceptions\TenantNotResolved;
use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Actions\SiteCrawlAction;
use App\Modules\X103\Models\SiteInventoryImage;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Modules\X103\Ui\SiteInventory;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('renders for a tenant', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('x-103.site-inventory'))
        ->assertOk()
        ->assertSee('Your current website');
});

it('refuses no-tenant requests', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    $this->withoutExceptionHandling();

    try {
        $this->get(route('x-103.site-inventory'));
    } catch (TenantNotResolved $e) {
        expect(true)->toBeTrue();
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('crawls a two-page fake site', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    Location::where('business_id', $biz->id)->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    $this->actingAs($owner);
    Tenancy::set($biz->id);

    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com' => Http::response(
            '<html><head><title>Home</title></head><body><h1>Welcome</h1><img src="/logo.png"><a href="/services">Services</a></body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
        'https://example.com/services' => Http::response(
            '<html><head><title>Services</title></head><body><h1>Our Services</h1><img src="/service1.png"></body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
    ]);

    $action = app(SiteCrawlAction::class);
    $result = $action->handle($biz->id, Location::where('business_id', $biz->id)->first()->id);
    expect($result)->toBe(['status' => 'fetched', 'pages' => 2, 'refused' => 0]);

    $pages = SiteInventoryPage::where('business_id', $biz->id)->get();
    expect($pages)->toHaveCount(2);

    $home = $pages->firstWhere('url', 'https://example.com');
    expect($home->title)->toBe('Home')
        ->and($home->status)->toBe('fetched')
        ->and($home->headings)->toBe(['Welcome'])
        ->and($home->image_urls)->toContain('https://example.com/logo.png');

    $services = $pages->firstWhere('url', 'https://example.com/services');
    expect($services->title)->toBe('Services')
        ->and($services->status)->toBe('fetched')
        ->and($services->headings)->toBe(['Our Services'])
        ->and($services->image_urls)->toContain('https://example.com/service1.png');
});

it('refuses when website is missing or unconfirmed', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    Location::where('business_id', $biz->id)->update([
        'website_url' => null,
    ]);

    Http::fake();
    $this->actingAs($owner);
    Tenancy::set($biz->id);

    Livewire::actingAs($owner)
        ->test(SiteInventory::class)
        ->call('crawl')
        ->assertDispatched('toast', message: 'Crawl refused: no_website');

    Http::assertNothingSent();
    expect(SiteInventoryPage::count())->toBe(0);
});

it('honours the max_pages registry cap', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    Location::where('business_id', $biz->id)->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    $this->actingAs($owner);
    Tenancy::set($biz->id);

    app(DefaultsRegistry::class)->set('sites.crawl.max_pages', 2, 'test');

    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com' => Http::response(
            '<html><body><a href="/page2">Page 2</a><a href="/page3">Page 3</a></body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
        'https://example.com/page2' => Http::response('<html><body>Page 2</body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/page3' => Http::response('<html><body>Page 3</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    Livewire::actingAs($owner)
        ->test(SiteInventory::class)
        ->call('crawl')
        ->assertDispatched('toast');

    $pages = SiteInventoryPage::where('business_id', $biz->id)->get();
    expect($pages)->toHaveCount(2);

    $page1 = $pages->firstWhere('url', 'https://example.com');
    expect($page1->status)->toBe('fetched')
        ->and($page1->url)->toBe('https://example.com');

    $page2 = $pages->firstWhere('url', 'https://example.com/page2');
    expect($page2->status)->toBe('fetched')
        ->and($page2->url)->toBe('https://example.com/page2');
});

it('copies images into tenant storage', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    $loc = Location::where('business_id', $biz->id)->first();
    $loc->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    Tenancy::set($biz->id);

    $page = SiteInventoryPage::create([
        'business_id' => $biz->id,
        'location_id' => $loc->id,
        'url' => 'https://example.com',
        'image_urls' => [
            'https://example.com/valid.png',
            'https://example.com/not-image.html',
            'https://example.com/too-big.png',
        ],
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Storage::fake('local');
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com/valid.png' => Http::response($pngBytes, 200, ['Content-Type' => 'image/png']),
        'https://example.com/not-image.html' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/too-big.png' => Http::response(str_repeat('a', 2000001), 200, ['Content-Type' => 'image/png']),
    ]);

    Livewire::actingAs($owner)
        ->test(SiteInventory::class)
        ->call('copyImages');

    $images = SiteInventoryImage::where('business_id', $biz->id)->get();
    expect($images)->toHaveCount(3);

    $valid = $images->firstWhere('source_url', 'https://example.com/valid.png');
    expect($valid->status)->toBe('stored')
        ->and($valid->mime)->toBe('image/png')
        ->and($valid->bytes)->toBe(strlen($pngBytes))
        ->and($valid->attribution)->toBe('example.com')
        ->and(Storage::disk('local')->exists($valid->path))->toBeTrue();

    $notImage = $images->firstWhere('source_url', 'https://example.com/not-image.html');
    expect($notImage->status)->toBe('refused')
        ->and($notImage->refusal_reason)->toBe('non_image');

    $tooBig = $images->firstWhere('source_url', 'https://example.com/too-big.png');
    expect($tooBig->status)->toBe('refused')
        ->and($tooBig->refusal_reason)->toBe('oversize');

    // re-run idempotent
    Livewire::actingAs($owner)
        ->test(SiteInventory::class)
        ->call('copyImages');

    $images = SiteInventoryImage::where('business_id', $biz->id)->get();
    expect($images)->toHaveCount(3);
});

it('caps copied images at max_per_site', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    $loc = Location::where('business_id', $biz->id)->first();
    $loc->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    Tenancy::set($biz->id);
    app(DefaultsRegistry::class)->set('sites.images.max_per_site', 1, 'test');

    $page = SiteInventoryPage::create([
        'business_id' => $biz->id,
        'location_id' => $loc->id,
        'url' => 'https://example.com',
        'image_urls' => [
            'https://example.com/img1.png',
            'https://example.com/img2.png',
        ],
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Storage::fake('local');
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com/img1.png' => Http::response($pngBytes, 200, ['Content-Type' => 'image/png']),
        'https://example.com/img2.png' => Http::response($pngBytes, 200, ['Content-Type' => 'image/png']),
    ]);

    Livewire::actingAs($owner)
        ->test(SiteInventory::class)
        ->call('copyImages');

    $images = SiteInventoryImage::where('business_id', $biz->id)->get();
    expect($images)->toHaveCount(1);
    expect($images->first()->source_url)->toBe('https://example.com/img1.png');
});

it('saves opening hours and shows the form', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
    Tenancy::set($biz->id);

    Livewire::actingAs($owner)->test(SiteInventory::class)
        ->set('hours.0.open', '08:00')
        ->set('hours.0.close', '17:00')
        ->set('hours.6.closed', true)
        ->call('saveHours')
        ->assertDispatched('toast', message: 'Hours saved — the next draft shows them in the contact section.');

    expect(Location::where('business_id', $biz->id)->first()->refresh()->opening_hours)
        ->toBe([['day' => 'Monday', 'open' => '08:00', 'close' => '17:00'], ['day' => 'Sunday', 'open' => 'Closed', 'close' => '']]);

    $this->actingAs($owner)->get(route('x-103.site-inventory'))->assertSee('Your opening hours');

    Livewire::actingAs($owner)->test(SiteInventory::class)
        ->set('hours.0.open', '8am')
        ->call('saveHours')
        ->assertDispatched('toast', message: 'Use HH:MM for Monday.');
});
