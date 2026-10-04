<?php

declare(strict_types=1);

use App\Enums\FetchRefusalReason;
use App\Enums\UserRole;
use App\Exceptions\TenantNotResolved;
use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Actions\SiteCrawlAction;
use App\Modules\X103\Jobs\SiteCrawlContinueJob;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteInventoryImage;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Modules\X103\Ui\SiteInventory;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
    expect($result)->toBe(['status' => 'fetched', 'pages' => 2, 'refused' => 0, 'queued' => 0]);

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

it('stores a page\'s own words and not its menu, footer or scripts', function () {
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
            '<html><head><title>Home</title><style>.x{color:red}</style></head><body>'
            .'<nav>Menu home menu about 5001</nav><header>Header strap 5002</header>'
            .'<main><h1>Welcome</h1><p>Distinctive main words 5003</p></main>'
            .'<footer>Footer copyright 5004</footer><script>var tracker = "Distinctive script 5005";</script>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
        'https://example.com/plain' => Http::response(
            '<html><body><nav>Menu plain 5006</nav><p>Distinctive body words 5007</p><script>var y = "Script plain 5008";</script></body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
    ]);

    app(SiteCrawlAction::class)->handle($biz->id, Location::where('business_id', $biz->id)->first()->id);

    $home = SiteInventoryPage::where('business_id', $biz->id)->where('url', 'https://example.com')->first();
    expect($home->text)->toContain('Distinctive main words 5003')
        ->and($home->text)->not->toContain('Menu home menu about 5001')
        ->and($home->text)->not->toContain('Header strap 5002')
        ->and($home->text)->not->toContain('Footer copyright 5004')
        ->and($home->text)->not->toContain('Distinctive script 5005')
        ->and($home->headings)->toBe(['Welcome']);
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

it('reads the brand colours and fonts from the home page and its own theme stylesheet, and shows them', function () {
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
            '<html><head><title>Home</title><meta name="theme-color" content="#1e5aa8">'
            .'<link rel="stylesheet" href="/wp-content/plugins/forms/forms.css">'
            .'<link rel="stylesheet" href="/wp-content/themes/bob/style.css">'
            .'<link rel="stylesheet" href="https://cdn.elsewhere.test/brand.css">'
            .'</head><body><main><h1>Welcome</h1></main></body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
        'https://example.com/wp-content/themes/bob/style.css' => Http::response('.btn{background:#e4572e;font-family:"Merriweather", serif}', 200, ['Content-Type' => 'text/css']),
        'https://example.com/wp-content/plugins/forms/forms.css' => Http::response('.f{color:#0000ff}', 200, ['Content-Type' => 'text/css']),
    ]);

    app(SiteCrawlAction::class)->handle($biz->id, Location::where('business_id', $biz->id)->first()->id);

    $home = SiteInventoryPage::where('business_id', $biz->id)->where('url', 'https://example.com')->first();
    expect($home->brand)->toEqual(['theme_color' => '#1e5aa8', 'colours' => ['#1e5aa8', '#e4572e'], 'fonts' => ['Merriweather']]);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'forms.css') || str_contains($r->url(), 'cdn.elsewhere.test'));

    Livewire::test(SiteInventory::class)
        ->assertSee('#e4572e')
        ->assertSee('Merriweather');
});

it('queues the pages the per-minute budget turns away and reads them a minute later', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    Location::where('business_id', $biz->id)->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    $this->actingAs($owner);
    Tenancy::set($biz->id);

    DB::table('fetch_sources')->where('key', 'tenant_site')->update(['rate_budget' => json_encode(['per_minute' => 2, 'per_day' => 2000])]);
    Queue::fake();
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com' => Http::response('<html><head><title>Home</title></head><body><a href="/a">A</a><a href="/b">B</a><a href="/c">C</a></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/a' => Http::response('<html><head><title>A</title></head><body><p>Page A 8601</p></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/b' => Http::response('<html><head><title>B</title></head><body><p>Page B 8602</p></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/c' => Http::response('<html><head><title>C</title></head><body><p>Page C 8603</p></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);
    $locationId = Location::where('business_id', $biz->id)->first()->id;

    $first = app(SiteCrawlAction::class)->handle($biz->id, $locationId);

    expect($first)->toBe(['status' => 'fetched', 'pages' => 2, 'refused' => 0, 'queued' => 2])
        ->and(SiteInventoryPage::where('business_id', $biz->id)->where('status', 'queued')->orderBy('url')->pluck('url')->all())
        ->toBe(['https://example.com/b', 'https://example.com/c'])
        ->and(SiteInventoryPage::where('business_id', $biz->id)->where('status', 'refused')->count())->toBe(0);
    Queue::assertPushed(SiteCrawlContinueJob::class, fn (SiteCrawlContinueJob $job) => $job->round === 2 && $job->businessId === $biz->id && $job->locationId === $locationId && $job->remaining === 23);
    Livewire::test(SiteInventory::class)->assertSee('a few pages a minute');

    $this->travel(2)->minutes();
    $second = app(SiteCrawlAction::class)->continue($biz->id, $locationId, 2, 23);

    expect($second)->toBe(['status' => 'fetched', 'pages' => 2, 'refused' => 0, 'queued' => 0])
        ->and(SiteInventoryPage::where('business_id', $biz->id)->where('status', 'fetched')->count())->toBe(4)
        ->and(SiteInventoryPage::where('business_id', $biz->id)->where('url', 'https://example.com/c')->value('text'))->toContain('Page C 8603');
    Queue::assertPushed(SiteCrawlContinueJob::class, 1);
});

it('stops resuming a paused crawl after its last round', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    Location::where('business_id', $biz->id)->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    $this->actingAs($owner);
    Tenancy::set($biz->id);

    DB::table('fetch_sources')->where('key', 'tenant_site')->update(['rate_budget' => json_encode(['per_minute' => 0, 'per_day' => 2000])]);
    Queue::fake();
    Http::fake();
    $locationId = Location::where('business_id', $biz->id)->first()->id;
    SiteInventoryPage::create(['business_id' => $biz->id, 'location_id' => $locationId, 'url' => 'https://example.com/x', 'status' => 'queued']);

    $last = app(SiteCrawlAction::class)->continue($biz->id, $locationId, SiteCrawlAction::MAX_ROUNDS, 25);

    expect($last)->toBe(['status' => 'fetched', 'pages' => 0, 'refused' => 0, 'queued' => 1]);
    Queue::assertNothingPushed();
    Http::assertNothingSent();

    app(SiteCrawlAction::class)->continue($biz->id, $locationId, 1, 25);
    Queue::assertPushed(SiteCrawlContinueJob::class, fn (SiteCrawlContinueJob $job) => $job->round === 2);
});

it('reads a resumed crawl whatever rows earlier crawls left behind', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    Location::where('business_id', $biz->id)->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    $this->actingAs($owner);
    Tenancy::set($biz->id);

    Queue::fake();
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com/b' => Http::response('<html><head><title>B</title></head><body><p>Queued page 8801</p></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/c' => Http::response('<html><head><title>C</title></head><body><p>Old refused page 8802</p></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);
    $locationId = Location::where('business_id', $biz->id)->first()->id;
    foreach (range(1, 30) as $i) {
        SiteInventoryPage::create(['business_id' => $biz->id, 'location_id' => $locationId, 'url' => "https://example.com/earlier-{$i}", 'status' => 'fetched', 'fetched_at' => now()->subDays(3)]);
    }
    SiteInventoryPage::create(['business_id' => $biz->id, 'location_id' => $locationId, 'url' => 'https://example.com/b', 'status' => 'queued']);
    SiteInventoryPage::create(['business_id' => $biz->id, 'location_id' => $locationId, 'url' => 'https://example.com/c', 'status' => 'refused', 'refusal_reason' => 'rate_budget', 'fetched_at' => now()]);

    $result = app(SiteCrawlAction::class)->continue($biz->id, $locationId, 2, 5);

    expect($result)->toBe(['status' => 'fetched', 'pages' => 2, 'refused' => 0, 'queued' => 0])
        ->and(SiteInventoryPage::where('business_id', $biz->id)->where('url', 'https://example.com/c')->value('text'))->toContain('Old refused page 8802')
        ->and(SiteCrawlAction::cutShort($biz->id, $locationId)->count())->toBe(0);
});

it('stops counting a page past the page limit as cut short', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    Location::where('business_id', $biz->id)->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    $this->actingAs($owner);
    Tenancy::set($biz->id);

    app(DefaultsRegistry::class)->set('sites.crawl.max_pages', 2, 'test');
    Queue::fake();
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com' => Http::response('<html><head><title>Home</title></head><body><p>Home</p></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/p1' => Http::response('<html><head><title>P1</title></head><body><p>P1</p></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);
    $locationId = Location::where('business_id', $biz->id)->first()->id;
    foreach (['p1', 'p2', 'p3'] as $slug) {
        SiteInventoryPage::create(['business_id' => $biz->id, 'location_id' => $locationId, 'url' => "https://example.com/{$slug}", 'status' => 'refused', 'refusal_reason' => 'rate_budget', 'fetched_at' => now()]);
    }

    $result = app(SiteCrawlAction::class)->handle($biz->id, $locationId);

    expect($result)->toBe(['status' => 'fetched', 'pages' => 2, 'refused' => 0, 'queued' => 0])
        ->and(SiteCrawlAction::cutShort($biz->id, $locationId)->count())->toBe(0)
        ->and(SiteInventoryPage::where('business_id', $biz->id)->where('url', 'https://example.com/p3')->value('refusal_reason'))->toBe(SiteCrawlAction::PAST_LIMIT);
    Queue::assertNothingPushed();
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/p2') || str_contains($r->url(), '/p3'));
    Livewire::test(SiteInventory::class)->assertSee('past the page limit');
});

it('reads Cloudflare-protected email addresses and never crawls them or a #fragment as pages', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    Location::where('business_id', $biz->id)->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    $this->actingAs($owner);
    Tenancy::set($biz->id);

    Queue::fake();
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com' => Http::response(
            '<html><head><title>Home</title></head><body>'
            .'<a href="/cdn-cgi/l/email-protection#422b2c242d6f7a7b727302273a232f322e276c212d2f">[email&#160;protected]</a>'
            .'<a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="1764767b72643a2f2e272557726f767a677b723974787a">[email&#160;protected]</a>'
            .'<a href="/services#pricing">Prices</a><a href="/services">Services</a>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
        'https://example.com/services' => Http::response('<html><head><title>Services</title></head><body><p>Services 8903</p></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);
    $locationId = Location::where('business_id', $biz->id)->first()->id;

    $result = app(SiteCrawlAction::class)->handle($biz->id, $locationId);

    expect($result)->toBe(['status' => 'fetched', 'pages' => 2, 'refused' => 0, 'queued' => 0])
        ->and(SiteInventoryPage::where('business_id', $biz->id)->where('url', 'like', '%cdn-cgi%')->count())->toBe(0)
        ->and(SiteInventoryPage::where('business_id', $biz->id)->where('url', 'like', '%#%')->count())->toBe(0)
        ->and(SiteInventoryPage::where('business_id', $biz->id)->where('url', 'https://example.com')->first()->emails)
        ->toEqualCanonicalizing(['info-8901@example.com', 'sales-8902@example.com']);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'cdn-cgi'));
});

it('forgets the pages and photos of a previous website when the new one is read', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

    Location::where('business_id', $biz->id)->update([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    $this->actingAs($owner);
    Tenancy::set($biz->id);

    $locationId = Location::where('business_id', $biz->id)->first()->id;
    $old = SiteInventoryPage::create(['business_id' => $biz->id, 'location_id' => $locationId, 'url' => 'https://old-site-9401.example/', 'status' => 'fetched', 'fetched_at' => now(), 'text' => 'Old site words', 'brand' => ['colours' => ['#ff0000']]]);
    SiteInventoryImage::create(['business_id' => $biz->id, 'page_id' => $old->id, 'source_url' => 'https://old-site-9401.example/hero.jpg', 'attribution' => 'old-site-9401.example', 'status' => 'stored', 'path' => 'inventory/old-9401.jpg']);

    Queue::fake();
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'https://example.com' => Http::response('<html><head><title>Home</title></head><body><p>New site words 9402</p></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    app(SiteCrawlAction::class)->handle($biz->id, $locationId);

    expect(SiteInventoryPage::where('business_id', $biz->id)->where('url', 'like', '%old-site-9401%')->count())->toBe(0)
        ->and(SiteInventoryImage::where('business_id', $biz->id)->count())->toBe(0)
        ->and(SiteInventoryPage::where('business_id', $biz->id)->where('url', 'https://example.com')->value('text'))->toContain('New site words 9402');
});

it('reads the products a store publishes on its pages, and puts their pictures first in line to be copied', function () {
    // Fixture taken from 'reads the brand colours and fonts from the home page and its own theme stylesheet, and shows them'.
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
            '<html><head><title>Shop</title>'
            .'<script type="application/ld+json">{"@type":"Product","name":"Folk slip mug 7511","url":"/products/mug","image":"/img/mug-7511.jpg","offers":{"price":"38","priceCurrency":"USD"}}</script>'
            .'</head><body><main><h1>Shop</h1><img src="/img/banner-7512.jpg" alt="Banner"></main></body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
    ]);

    app(SiteCrawlAction::class)->handle($biz->id, Location::where('business_id', $biz->id)->first()->id);

    $home = SiteInventoryPage::where('business_id', $biz->id)->where('url', 'https://example.com')->first();
    // jsonb keeps a list's order but not an object's keys, so the product is compared by key and value (toEqual), the list by order (toBe).
    expect($home->products)->toEqual([['name' => 'Folk slip mug 7511', 'url' => 'https://example.com/products/mug', 'price_text' => '$38', 'image_url' => 'https://example.com/img/mug-7511.jpg']])
        ->and($home->image_urls)->toBe(['https://example.com/img/mug-7511.jpg', 'https://example.com/img/banner-7512.jpg']);
});
