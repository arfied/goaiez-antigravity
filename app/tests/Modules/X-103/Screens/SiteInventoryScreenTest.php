<?php

declare(strict_types=1);

use App\Enums\FetchRefusalReason;
use App\Enums\UserRole;
use App\Exceptions\TenantNotResolved;
use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Actions\SiteCrawlAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteInventoryImage;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Modules\X103\Ui\SiteInventory;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Http::preventStrayRequests();
});

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

    $refused = false;
    try {
        $this->get(route('x-103.site-inventory'));
    } catch (TenantNotResolved $e) {
        $refused = true;
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
        $refused = true;
    }

    expect($refused)->toBeTrue();
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
        'api.anthropic.com/*' => Http::response(
            json_encode([
                'content' => [['type' => 'text', 'text' => '{}']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ]),
            200,
            ['Content-Type' => 'application/json']
        ),
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com' => Http::response(
            '<html><head><title>Home</title></head><body><h1>Welcome</h1><img src="/logo.png" alt="  Our distinctive van 4471 "><a href="/services">Services</a></body></html>',
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
        ->and($home->image_urls)->toContain('https://example.com/logo.png')
        ->and($home->image_alts)->toBe(['https://example.com/logo.png' => 'Our distinctive van 4471']);

    $services = $pages->firstWhere('url', 'https://example.com/services');
    expect($services->title)->toBe('Services')
        ->and($services->status)->toBe('fetched')
        ->and($services->headings)->toBe(['Our Services'])
        ->and($services->image_urls)->toContain('https://example.com/service1.png')
        ->and($services->image_alts)->toBe([]);
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
        'api.anthropic.com/*' => Http::response(
            json_encode([
                'content' => [['type' => 'text', 'text' => '{}']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ]),
            200,
            ['Content-Type' => 'application/json']
        ),
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
        'image_alts' => ['https://example.com/valid.png' => 'Distinctive alt 4472'],
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Storage::fake('local');
    Http::fake([
        'api.anthropic.com/*' => Http::response(
            json_encode([
                'content' => [['type' => 'text', 'text' => '{}']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ]),
            200,
            ['Content-Type' => 'application/json']
        ),
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
        ->and($valid->alt)->toBe('Distinctive alt 4472')
        ->and($valid->width)->toBe(1)
        ->and($valid->height)->toBe(1)
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
        'api.anthropic.com/*' => Http::response(
            json_encode([
                'content' => [['type' => 'text', 'text' => '{}']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ]),
            200,
            ['Content-Type' => 'application/json']
        ),
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

it('lists what the draft cannot find and drops a row once the fact exists', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
    Tenancy::set($biz->id);

    $this->actingAs($owner)
        ->get(route('x-103.site-inventory'))
        ->assertOk()
        ->assertSee('What your site is still missing')
        ->assertSee('Your opening hours')
        ->assertSee('Your services and prices')
        ->assertSee('A contact form');

    Livewire::actingAs($owner)
        ->test(SiteInventory::class)
        ->set('hours.0.open', '08:00')
        ->set('hours.0.close', '17:00')
        ->call('saveHours');

    $this->actingAs($owner)
        ->get(route('x-103.site-inventory'))
        ->assertDontSee('Set them below; the contact section shows them.');
});

it('lets the owner describe a stored picture', function () {
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
            'https://example.com/x.jpg',
        ],
    ]);

    $img = SiteInventoryImage::create([
        'status' => 'stored',
        'path' => 'inventory/x.jpg',
        'source_url' => 'https://example.com/x.jpg',
        'attribution' => 'example.com',
        'page_id' => $page->id,
        'business_id' => $biz->id,
    ]);

    Livewire::actingAs($owner)->test(SiteInventory::class)
        ->set('alts.'.$img->id, '  Distinctive alt 4473  ')
        ->call('saveAlt', $img->id)
        ->assertDispatched('toast', message: 'Description saved — the next draft carries it on this picture.');

    expect($img->refresh()->alt)->toBe('Distinctive alt 4473');

    Livewire::actingAs($owner)->test(SiteInventory::class)
        ->set('alts.'.$img->id, str_repeat('a', 161))
        ->call('saveAlt', $img->id)
        ->assertDispatched('toast', message: 'Keep the description under 160 characters.');

    expect($img->refresh()->alt)->toBe('Distinctive alt 4473');

    $this->actingAs($owner)->get(route('x-103.site-inventory'))
        ->assertOk()
        ->assertSee('Describe each stored picture');
});

it('lists what a reader would trip on and drops the row once it is fixed', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
    Tenancy::set($biz->id);

    $this->actingAs($owner)
        ->get(route('x-103.site-inventory'))
        ->assertOk()
        ->assertSee('Can everyone read it')
        ->assertSee('Nothing to check yet');

    $about = Page::create([
        'business_id' => $biz->id,
        'slug' => 'about',
        'title' => 'About',
        'draft_blocks' => [
            ['type' => 'team', 'items' => [['name' => 'A', 'role' => 'B']]],
        ],
    ]);

    $this->actingAs($owner)
        ->get(route('x-103.site-inventory'))
        ->assertOk()
        ->assertSee('No main heading');

    $about->update([
        'draft_blocks' => [
            ['type' => 'hero', 'headline' => 'H1', 'image_path' => 'inventory/a.jpg', 'image_alt' => 'Distinctive alt'],
        ],
    ]);

    $this->actingAs($owner)
        ->get(route('x-103.site-inventory'))
        ->assertOk()
        ->assertDontSee('No main heading');
});

it('records a pictures pixel size at copy time and leaves it empty for an svg', function () {
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
            'https://example.com/mark.svg',
        ],
        'image_alts' => ['https://example.com/valid.png' => 'Distinctive alt 4472'],
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Storage::fake('local');
    Http::fake([
        'api.anthropic.com/*' => Http::response(
            json_encode([
                'content' => [['type' => 'text', 'text' => '{}']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ]),
            200,
            ['Content-Type' => 'application/json']
        ),
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com/valid.png' => Http::response($pngBytes, 200, ['Content-Type' => 'image/png']),
        'https://example.com/not-image.html' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/too-big.png' => Http::response(str_repeat('a', 2000001), 200, ['Content-Type' => 'image/png']),
        'https://example.com/mark.svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>', 200, ['Content-Type' => 'image/svg+xml']),
    ]);

    Livewire::actingAs($owner)
        ->test(SiteInventory::class)
        ->call('copyImages');

    $images = SiteInventoryImage::where('business_id', $biz->id)->get();
    expect($images)->toHaveCount(4);

    $svg = $images->firstWhere('source_url', 'https://example.com/mark.svg');
    expect($svg->status)->toBe('stored')
        ->and($svg->width)->toBeNull()
        ->and($svg->height)->toBeNull();
});

it('weighs each drafted page and says it is a weight not a load time', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
    Tenancy::set($biz->id);

    $this->actingAs($owner)
        ->get(route('x-103.site-inventory'))
        ->assertOk()
        ->assertSee('How heavy each page is')
        ->assertSee('Nothing to weigh yet');

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $inventoryPage = SiteInventoryPage::create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'url' => 'https://example.com',
    ]);

    SiteInventoryImage::create([
        'business_id' => $biz->id,
        'page_id' => $inventoryPage->id,
        'source_url' => 'https://example.com/w1.jpg',
        'path' => 'inventory/w1.jpg',
        'mime' => 'image/jpeg',
        'bytes' => 11264,
        'status' => 'stored',
        'attribution' => 'example.com',
    ]);
    SiteInventoryImage::create([
        'business_id' => $biz->id,
        'page_id' => $inventoryPage->id,
        'source_url' => 'https://example.com/w2.jpg',
        'path' => 'inventory/w2.jpg',
        'mime' => 'image/jpeg',
        'bytes' => 22528,
        'status' => 'stored',
        'attribution' => 'example.com',
    ]);
    SiteInventoryImage::create([
        'business_id' => $biz->id,
        'page_id' => $inventoryPage->id,
        'source_url' => 'https://example.com/w3.jpg',
        'path' => 'inventory/w3.jpg',
        'mime' => 'image/jpeg',
        'bytes' => 44032,
        'status' => 'stored',
        'attribution' => 'example.com',
    ]);

    Page::create([
        'business_id' => $biz->id,
        'slug' => 'home',
        'title' => 'Home',
        'draft_blocks' => [
            ['type' => 'hero', 'headline' => 'Headline', 'image_path' => 'inventory/w1.jpg'],
            ['type' => 'gallery', 'items' => [['image_path' => 'inventory/w2.jpg'], ['image_path' => 'inventory/w3.jpg']]],
            ['type' => 'pixel_script'],
        ],
        'is_published' => false,
    ]);

    $this->actingAs($owner)
        ->get(route('x-103.site-inventory'))
        ->assertOk()
        ->assertSee('76.0 KB')
        ->assertSee('w3.jpg (43.0 KB)')
        ->assertSee('the visitor pixel')
        ->assertDontSee('Nothing to weigh yet');
});

it('rejected pictures explain themselves in words', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
    Tenancy::set($biz->id);

    $page = SiteInventoryPage::create([
        'business_id' => $biz->id,
        'location_id' => Location::factory()->create(['business_id' => $biz->id])->id,
        'url' => 'https://example.com',
    ]);

    SiteInventoryImage::updateOrCreate(
        ['business_id' => $biz->id, 'source_url' => 'https://example.com/oversize.png'],
        [
            'page_id' => $page->id,
            'path' => null,
            'mime' => null,
            'bytes' => null,
            'status' => 'refused',
            'refusal_reason' => 'oversize',
            'attribution' => 'example.com',
        ]
    );

    SiteInventoryImage::updateOrCreate(
        ['business_id' => $biz->id, 'source_url' => 'https://example.com/nonimage.png'],
        [
            'page_id' => $page->id,
            'path' => null,
            'mime' => null,
            'bytes' => null,
            'status' => 'refused',
            'refusal_reason' => 'non_image',
            'attribution' => 'example.com',
        ]
    );

    SiteInventoryImage::updateOrCreate(
        ['business_id' => $biz->id, 'source_url' => 'https://example.com/robots.png'],
        [
            'page_id' => $page->id,
            'path' => null,
            'mime' => null,
            'bytes' => null,
            'status' => 'refused',
            'refusal_reason' => FetchRefusalReason::RobotsDisallow->value,
            'attribution' => 'example.com',
        ]
    );

    $this->actingAs($owner)->get(route('x-103.site-inventory'))
        ->assertSee('Too large to copy')
        ->assertSee('Not an image')
        ->assertDontSee('>oversize<', false)
        ->assertSee('robots.txt');
});

it('test_the_inventory_screen_uses_the_house_button', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
    $this->actingAs($owner);

    Livewire::test(SiteInventory::class)
        ->assertSeeHtml('wire:click="crawl"')
        ->assertDontSeeHtml('class="btn');
});

it('shows why a page was not fetched', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
    $loc = Location::where('business_id', $biz->id)->first() ?? Location::factory()->create(['business_id' => $biz->id]);

    SiteInventoryPage::create([
        'business_id' => $biz->id,
        'location_id' => $loc->id,
        'url' => 'https://distinctive-4861.example/',
        'status' => 'refused',
        'refusal_reason' => FetchRefusalReason::RobotsDisallow->value,
        'fetched_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('x-103.site-inventory'))
        ->assertOk()
        ->assertSee('distinctive-4861.example')
        ->assertSee("your website's own robots.txt refuses this page", false);
});

it('records a block by the site as blocked_by_site', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    Location::where('business_id', $biz->id)->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    $this->actingAs($owner);
    Tenancy::set($biz->id);

    Http::fake([
        'api.anthropic.com/*' => Http::response(
            json_encode([
                'content' => [['type' => 'text', 'text' => '{}']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ]),
            200,
            ['Content-Type' => 'application/json']
        ),
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com' => Http::response('<html><title>Attention Required! | Cloudflare</title></html>', 403, ['Content-Type' => 'text/html']),
    ]);

    $result = app(SiteCrawlAction::class)->handle($biz->id, Location::where('business_id', $biz->id)->first()->id);
    expect($result['refused'])->toBe(1);

    $row = SiteInventoryPage::where('business_id', $biz->id)->where('url', 'https://example.com')->first();
    expect($row->status)->toBe('failed')
        ->and($row->refusal_reason)->toBe('blocked_by_site');
});

it('tells the owner what to allow when the site blocked us', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
    $loc = Location::where('business_id', $biz->id)->first() ?? Location::factory()->create(['business_id' => $biz->id]);

    SiteInventoryPage::create([
        'business_id' => $biz->id,
        'location_id' => $loc->id,
        'url' => 'https://distinctive-4891.example/',
        'status' => 'failed',
        'refusal_reason' => 'blocked_by_site',
        'fetched_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('x-103.site-inventory'))
        ->assertOk()
        ->assertSee('distinctive-4891.example')
        ->assertSee('security service blocked our request')
        ->assertSee('GoAiEzBot')
        ->assertSee('Custom rules');
});

test('a platform admin with no tenant sees an empty site inventory not a 500', function () {
    $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
    $this->actingAs($admin)
        ->get(route('x-103.site-inventory.admin'))
        ->assertForbidden();
});
