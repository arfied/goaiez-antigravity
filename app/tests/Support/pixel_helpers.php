<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use App\Services\Pixel\PixelEnrichment;
use App\Services\Pixel\PixelKeys;
use App\Services\Widgets\WidgetPlugins;
use App\Support\Tenancy;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Shared fixtures for the pixel collector
|--------------------------------------------------------------------------
|
| ⚠️ **HERE RATHER THAN IN A TEST FILE, AND `CLAUDE.md` SAYS WHY TWICE.** Two
| files drive the collector — `Feature/Pixel/PixelCollectorTest` and
| `Feature/Pixel/PixelIngestToReplayTest` — and a global function declared in one
| test file and used in another works only because Pest happens to have loaded
| the first. **A duplicated global helper name is one of the four documented
| causes of a run that prints zero bytes and exits non-zero** (694, 808), and
| load-order-dependent availability is the same hazard waiting to happen. Shared
| helpers live in `tests/Support/`; `architecture_helpers.php` and
| `tenant_helpers.php` are the precedent and `tests/Pest.php` requires all three.
|
*/

/**
 * A tenant whose pixel is installable: a public key, and one named website.
 *
 * ⚠️ **BUILT THROUGH `Business::provision()` AND THE REAL PROVISIONER, NEVER
 * THROUGH `BusinessFactory`.** The factory fills `pixel_tenant_id` and the
 * application did not, which is the whole of decision 272's shape in this
 * slice — a fixture that used the factory would have proven the collector worked
 * against a key no real tenant has.
 */
function pixelTenant(string $name = 'Ledger Diner', string $host = 'ledger.test'): Business
{
    $business = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => $name,
    ]);

    $location = Location::query()->create(['name' => $name]);

    $plugin = app(WidgetPlugins::class)->provisionFor($location);

    app(WidgetPlugins::class)->setAllowedDomains($plugin, [$host], 'test');

    app(PixelKeys::class)->ensureFor($business);

    return $business->refresh();
}

/**
 * The public key a tenant's pixel would present.
 *
 * ⚠️ **A HELPER RATHER THAN A PROPERTY READ, BECAUSE THE PROPERTY IS GONE.**
 * `businesses.pixel_tenant_id` was dropped with this slice — see the `pixel_keys`
 * migration — and a test still reading it would fail loudly rather than quietly
 * comparing two nulls.
 */
function pixelKeyFor(Business $business): string
{
    // ⚠️ **WRAPPED, BECAUSE `forBusiness()` IS A SCOPED READ AND MOST TESTS HERE
    // HAVE JUST SWITCHED OR CLEARED TENANTS.** Unwrapped it threw *"No tenant in
    // context"* in two multi-tenant tests and returned another tenant's null in a
    // third — which is `Tenancy::idOrFail()` failing loudly exactly as `PixelKey`'s
    // docblock says a scoped model should, in the harness rather than in
    // production.
    return (string) Tenancy::actingAs(
        (int) $business->getKey(),
        static fn (): ?string => app(PixelKeys::class)->forBusiness($business),
    );
}

/**
 * A batch shaped exactly like `resources/js/pixel.js`'s `flush()`.
 *
 * ⚠️ Read off the bundle rather than remembered. The envelope is per request and
 * the events are a batch, which is why an L0 receipt is one HTTP request and not
 * one event (decision 4874).
 *
 * @param  list<array<string, mixed>>  $events
 */
function pixelBody(string $key, array $events = []): string
{
    // ⛔ **`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, BECAUSE THAT IS WHAT
    // A BROWSER SENDS AND THE DEFAULT FLAGS HID A LIVE MUTANT.** `JSON.stringify`
    // never escapes `/` and never escapes non-ASCII; PHP's `json_encode` does
    // both by default. With the defaults here, a fixture body and a PHP
    // *re-encoding* of its decoded form were byte-identical — so the mutation
    // that replaced the archived raw body with `json_encode($payload)`
    // **survived**, and the one test named for byte fidelity could not see it.
    // 4572's finding exactly: the mutant was not too small, the fixture was too
    // clean. Every `page_url` in a real batch now differs between the two.
    return (string) json_encode([
        'k' => $key,
        'schema_version' => 1,
        'sent_at' => '2026-08-18T12:00:00.000Z',
        'device' => [
            'screen_w' => 390,
            'screen_h' => 844,
            'viewport_w' => 390,
            'viewport_h' => 700,
            'language' => 'en-US',
            'timezone' => 'America/Chicago',
            'cores' => 8,
            // A float from a real browser — `navigator.deviceMemory` is 0.25 on a
            // small phone. It is here because L0 must archive it verbatim and
            // `CanonicalJson` refuses floats; the receipt survives that only
            // because the payload is an opaque string.
            'memory' => 0.25,
            'touch' => 5,
            'network' => '4g',
            'webdriver' => false,
        ],
        'referrer_host' => 'search.test',
        'referrer_path' => '/q',
        'utm' => ['source' => 'google', 'medium' => 'cpc', 'campaign' => 'spring', 'term' => '', 'content' => ''],
        'click_id' => ['gclid' => '', 'fbclid' => '', 'msclkid' => ''],
        'events' => $events === [] ? [pixelEvent()] : $events,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * One event, in the shape `record()` pushes onto the queue.
 *
 * @param  array<string, mixed>  $properties
 * @return array<string, mixed>
 */
function pixelEvent(string $type = 'pageview', array $properties = []): array
{
    return [
        'event_id' => (string) Str::uuid(),
        'type' => $type,
        'occurred_at' => '2026-08-18T12:00:00.000Z',
        'session_id' => (string) Str::uuid(),
        'anonymous_id' => (string) Str::uuid(),
        'consent_state' => 'unknown',
        'page_url' => 'https://ledger.test/menu',
        'page_path' => '/menu',
        'page_title' => 'Menu',
        'properties' => $properties,
    ];
}

/**
 * A `PixelEnrichment` fixture for a direct `PixelCollector::receive()` call —
 * decision 5000s. Every value is the same "nothing known" default
 * `PixelEnrichment`'s own constructor would produce for a request with no
 * User-Agent and no resolvable address, since none of the tests calling
 * `receive()` directly are about §11 row 8.
 */
function pixelEnrichment(): PixelEnrichment
{
    return new PixelEnrichment(null, 'unknown', null, 'unknown');
}

/**
 * A real Chrome-on-Windows User-Agent, for the tests that go through the real
 * route and are about §11 row 8 — read off a genuine browser rather than
 * invented, on `docs/FAILURE-SHAPES.md`'s "Verify a vendor string, price or
 * parameter against the raw artefact" rule.
 */
const PIXEL_TEST_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
    .'(KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

/**
 * POST a body at the collector the way a browser does.
 *
 * ⚠️ **`text/plain`, WHICH IS NOT A DETAIL.** It is what the bundle sends,
 * because `POST` + `text/plain` is a CORS-simple request and therefore needs no
 * preflight on a visitor's first page load. Laravel does not parse it, which is
 * the whole reason `StorePixelBatchRequest` decodes the raw body itself — a test
 * that posted JSON would exercise a path the pixel never takes.
 *
 * @param  string|null  $gpc  The `Sec-GPC` header to send, or null to send
 *                            none — decision 5000s, §11 row 7.
 */
function postPixel(
    string $body,
    ?string $origin = 'https://ledger.test',
    ?string $userAgent = PIXEL_TEST_USER_AGENT,
    ?string $gpc = null,
): TestResponse {
    $server = ['CONTENT_TYPE' => 'text/plain;charset=UTF-8'];

    if ($origin !== null) {
        $server['HTTP_ORIGIN'] = $origin;
    }

    if ($userAgent !== null) {
        $server['HTTP_USER_AGENT'] = $userAgent;
    }

    if ($gpc !== null) {
        $server['HTTP_SEC_GPC'] = $gpc;
    }

    return test()->call('POST', '/api/pixel/e', [], [], [], $server, $body);
}
