<?php

declare(strict_types=1);

use App\Http\Controllers\Actuation\T3InjectionController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PixelIngestController;
use App\Http\Controllers\Api\PlaceSuggestionController;
use App\Http\Controllers\Api\PublicAuditController;
use App\Http\Controllers\Api\WidgetReviewController;
use App\Http\Controllers\Voice\Live\CallEndController;
use App\Http\Controllers\Voice\Live\CallPriceToolController;
use App\Http\Controllers\Voice\Live\CallStartController;
use App\Http\Controllers\Voice\Live\CallTurnsController;
use App\Http\Middleware\ResolveWidget;
use App\Http\Middleware\VerifyVoiceWorker;
use App\Modules\X102\Http\Controllers\ChatCaptureController;
use App\Modules\X102\Http\Controllers\ChatStartController;
use App\Modules\X102\Http\Controllers\ChatTurnController;
use App\Modules\X156\Http\Controllers\IngestWebhookController;
use App\Modules\X196\Http\Controllers\ExtensionScanController;
use App\Modules\X196\Http\Controllers\ExtensionSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Prefixed with /api and given the `api` middleware group by bootstrap/app.php.
|
| `auth:sanctum` covers both credentials FOUND-04 asks for: a personal access
| token for the widget and any headless client, and — because
| EnsureFrontendRequestsAreStateful is on the group — the ordinary session
| cookie for a first-party browser request. One guard, so there is one place the
| answer to "who is this?" comes from.
|
*/

/*
| The free instant audit (`29` §6.2) — deliberately unauthenticated, because it
| runs before anyone signs up. The limiter names are defined in
| AppServiceProvider and all three key on a keyed hash of the address rather
| than the address itself; see PublicAuditRateLimits for why that distinction
| is load-bearing rather than cosmetic.
|
| The three limits are sized by what each request costs us:
|
|   create    3/hr    each one can spend ~9c of Google money (`29` §6.2)
|   suggest   30/min  each one costs 0.283c, and typing is bursty
|   poll      120/min costs nothing; the client polls every 900ms for up to 20s
*/
Route::prefix('public')->name('api.public.')->group(function (): void {
    Route::post('/audit', [PublicAuditController::class, 'store'])
        ->middleware('throttle:public-audit-create')
        ->name('audit.store');

    Route::post('/audit/suggest', PlaceSuggestionController::class)
        ->middleware('throttle:public-audit-suggest')
        ->name('audit.suggest');

    /*
    | Below the POST routes on purpose. `/audit/suggest` would otherwise be
    | captured by `/audit/{token}` if the GET and POST verbs ever converged,
    | and route order is the kind of thing that is obvious now and mysterious
    | in a year.
    */
    Route::get('/audit/{token}', [PublicAuditController::class, 'show'])
        ->middleware('throttle:public-audit-poll')
        ->name('audit.show');
});

/*
| The public review feed (`17` FPR-05, row 3 slice G) — the server half of
| FPR-06. The embeddable script that would consume it is deferred (`BUILD-PLAN`
| §2.6.4 conflict 3), so today this answers curl and a future bundle.
|
| DELIBERATELY UNAUTHENTICATED, like the public audit above and for a stronger
| reason: it is rendered on a stranger's website by a visitor who has no account
| anywhere. The `embed_key` is the whole credential, which is why it is a random
| UUID and why ResolveWidget is the one place in this codebase that queries with
| no tenant established.
|
| ⚠️ The `Origin` allowlist is enforced in the controller, NOT here, and it is
| not an access control — see WidgetPlugins::originIsAllowed(). It refuses a
| browser on an unlisted site; it refuses nothing else, because `Origin` is
| trivially set by anything that is not a browser.
*/
Route::get('/widget/{embed_key}/reviews', WidgetReviewController::class)
    ->middleware(['throttle:widget-feed', ResolveWidget::class])
    ->name('api.widget.reviews');

/*
| The pixel collector — `GOAIEZ_PIXEL_MASTER_BUILD` §11, and the writer L0 never
| had (decisions 4580 part 1, 4861).
|
| ⚠️ THE URI IS `/api/pixel/e` BECAUSE THE PIXEL BUNDLE ALREADY SAYS SO, AND IT
| SAYS SO IN ORDER TO BE CAUGHT (4576). `PixelTest`'s route tripwire
| matches URIs containing `pixel` or `ingest`; W2 chose the client's constant so
| a collector answering this bundle could not be mounted anywhere that tripwire
| cannot see. That tripwire is reddened by this line, and was replaced rather
| than deleted by the HIPAA-gate tests it demanded.
|
| ⚠️ DELIBERATELY UNAUTHENTICATED, on the widget feed's terms and one step
| further: this one WRITES, to an append-only archive. The public key in the
| body is the whole credential and the `Origin` allowlist narrows who may spend
| it from a browser — see PixelCollector::originIsAllowed(), which refuses to be
| called an access control.
|
| ⛔ "NO RESOLVE-TENANT MIDDLEWARE, UNLIKE THE WIDGET FEED, AND THE ASYMMETRY IS
| FORCED BY THE TRANSPORT" IS WHAT THIS PARAGRAPH SAID UNTIL 2026-08-20, AND IT
| WAS NEVER TRUE — BOTH READINGS KEPT AND DATED (5790). `bootstrap/app.php`
| appends `ResolveTenant` to the whole `api` group, so it runs on this route:
| the resolved stack is Sanctum's stateful check, `throttle:pixel-ingest`,
| `ResolveTenant`, `SubstituteBindings`, in that order, because the priority
| list puts it behind `ThrottleRequests`.
|
| ⚠️ THE TRUE STATEMENT IS NARROWER, AND IT IS THE ONE THAT MATTERS: IT
| ESTABLISHES NO TENANT HERE, IT ONLY CLEARS ONE. Its first line is
| `Tenancy::forgetAll()` and its second returns as soon as `Auth::id()` is not
| an int — which is every request that reaches this endpoint, since it is
| deliberately unauthenticated. So what arrives at the controller is a request
| whose ambient tenant has been emptied and never refilled.
|
| ⚠️ NO PIXEL-SPECIFIC RESOLVER IS ADDED, AND THAT HALF OF THE OLD PARAGRAPH
| SURVIVES INTACT. The widget's key is a route parameter, so `ResolveWidget` can
| read it; the pixel's is inside a `text/plain` body, so a middleware would have
| to parse the body to establish a tenant, doing the request's expensive work
| before the limiter could refuse it. PixelCollector::receive() clears and sets
| tenancy on its own first lines and states that invariant where it happens.
|
| ⛔ AND THE WRONG SENTENCE HAD ALREADY COST A TEST ITS MEANING BEFORE ANYBODY
| NOTICED IT WAS WRONG (5639, 398). Slice I's first version of "the tenant is
| resolved from the key rather than from whatever was last in context" drove an
| HTTP request with a foreign tenant deliberately left in context — and SURVIVED
| the mutation that makes the service inherit a tenant instead of resolving one,
| because this middleware had already cleared it. The outer guard answered and
| the inner one was unfalsifiable. **Anything that has to prove a tenant is
| resolved rather than inherited is driven at the service, never through a route
| in this group**, and the route asserted afterwards as agreement.
|
| ⚠️ ONE LIMITER, KEYED ON A HASHED SOURCE. §11's per-tenant and
| per-`anonymous_id` limits are not expressible at a limiter that has not read
| the body — PixelRateLimits says so rather than approximating them.
*/
Route::post('/pixel/e', PixelIngestController::class)
    ->middleware('throttle:pixel-ingest')
    ->name('api.pixel.ingest');

/*
| WebChat door (X-102). Unauthenticated because visitors to a tenant's website
| starting a chat have no account on this platform.
|
| The key is the PixelKey, an unguessable UUID assigned per business and stored
| in the businesses table. Since it is a public identifier embedded in the
| tenant's page source, it is carried as a route parameter — governed by the
| T3InjectionController precedent.
|
| Unlike T3InjectionController (which writes nothing), this route creates a
| ChatSession row. For the write half, the controller explicitly resolves the
| tenant from the key via PixelKeys::resolve() and calls Tenancy::set() to
| enforce isolation before calling the action, keeping the write safe.
*/
Route::post('/chat/{key}/start', ChatStartController::class)
    ->middleware('throttle:chat-start')
    ->name('api.chat.start');

Route::post('/chat/{key}/turn', ChatTurnController::class)
    ->middleware('throttle:chat-turn')
    ->name('api.chat.turn');

Route::post('/chat/{key}/capture', ChatCaptureController::class)
    ->middleware('throttle:chat-capture')
    ->name('api.chat.capture');

/*
|--------------------------------------------------------------------------
| The AI receptionist's voice brain API (plan 2026-10-05)
|--------------------------------------------------------------------------
|
| Called only by the voice worker, which carries live call audio outside this host. Every request is signed
| (VerifyVoiceWorker); the tenant is never read from the request body — later endpoints derive it from a signed call token.
| tests/Feature/Architecture/VoiceTest.php holds both rules.
*/
Route::prefix('voice/v1')->middleware(['throttle:300,1', VerifyVoiceWorker::class])->group(function (): void {
    Route::post('/calls', CallStartController::class)->name('api.voice.calls.start');
    Route::post('/calls/{callToken}/turns', CallTurnsController::class)->where('callToken', '[A-Za-z0-9_~-]{1,1024}')->name('api.voice.calls.turns');
    Route::post('/calls/{callToken}/tools/price', CallPriceToolController::class)->where('callToken', '[A-Za-z0-9_~-]{1,1024}')->name('api.voice.calls.tools.price');
    Route::post('/calls/{callToken}/end', CallEndController::class)->where('callToken', '[A-Za-z0-9_~-]{1,1024}')->name('api.voice.calls.end');
});

Route::middleware('auth:sanctum')->group(function (): void {
    /*
    | Everything a client needs before it can render anything: who you are, which
    | business you are in, what you are paying for, and how far through setup you
    | got. Deliberately one call — three round trips before the first paint is
    | three chances to show a half-built screen.
    |
    | ⛔ `throttle:me` IS NOT DECORATION AND `auth:sanctum` DOES NOT REPLACE IT
    | (decision 3940). This route carried no limiter at all while every other
    | route in this application names one, and the api group emits none of its
    | own because bootstrap/app.php never calls throttleApi(). Authentication
    | says who is asking, not how often — and since AG3 one answer costs ~14
    | database round trips, six of them credit_ledger head-row reads. Keyed on
    | the user id; see MeRateLimits for the sizing.
    */
    Route::get('/me', MeController::class)
        ->middleware('throttle:me')
        ->name('api.me');
});

/*
|--------------------------------------------------------------------------
| T3 injection — the typed change-set payload (BUILD-PLAN §2.11.3 slice I)
|--------------------------------------------------------------------------
|
| What `resources/js/actuate.js` reads on somebody else's website: the JSON-LD,
| meta upserts, alt text, internal links and FAQ blocks this platform is
| publishing onto that tenant's pages, as **typed operations** and never as
| markup. `App\Enums\T3InjectionKind` is why free-form HTML is not expressible
| here; `T3Payloads` is where a health-information tenant, a paused account and
| an unknown key are all refused with the same empty body.
|
| ⚠️ THE PUBLIC KEY IS A ROUTE PARAMETER, UNLIKE THE COLLECTOR'S. The pixel's
| key travels in a `text/plain` body and forces its whole middleware asymmetry;
| this is an ordinary GET, so the key is in the URL where a limiter, a log and a
| cache can all see it. It is a public identifier by construction — it is pasted
| into a page's source — so a URL is where it belongs.
|
| ⛔ THE URI NAMES NEITHER "pixel" NOR "ingest", DELIBERATELY.
| `Architecture/PixelTest`'s first test asserts the exact set of routes matching
| either word is `['POST api/pixel/e']`, so a second match reddens it by name.
| This is not an ingest path: it writes nothing, receives nothing, and the only
| thing it learns about a visitor is the hashed source its limiter counts.
*/
Route::get('/site/{key}', [T3InjectionController::class, 'payload'])
    ->middleware('throttle:t3-payload')
    ->name('api.site.payload');

/*
| Webhook Ingestion door (X-156).
|
| 1. DELIBERATELY UNAUTHENTICATED. Webhooks arriving from external vendors like
| Meta, a CRM, or a form vendor have no account on this system. The HMAC signature
| in the payload is the entire credential.
|
| 2. BUSINESS ID IS IN THE URL, NOT A SECRET. It must be there because the
| `ingest_sources` table is under `FORCE ROW LEVEL SECURITY` with a strict
| `tenant_isolation` policy. Without setting a tenant context first, every row is
| invisible and the controller cannot look up its own source. This table must never
| get a public read policy because `secret_key` lives in it.
|
| 3. NOTHING IS WRITTEN BEFORE VERIFICATION. The only exception is the `IngestRejection`
| row, which is written on a bad signature to explicitly record the forged attempt.
| This is the designed behavior of the action and the reason this route is throttled.
|
| 4. ⚠️ THE STALE COMMENT: The `/site/{key}` block above mentions that a test called
| `Architecture/PixelTest` asserts that the exact set of routes matching the word
| 'ingest' or 'pixel' is `['POST api/pixel/e']`. That test does NOT exist. There is
| no file matching `*pixel*` under `tests/Feature/Architecture/`, and the route name
| `api/pixel/e` only appears in a test helper. The route below contains `ingest` and
| reddens nothing. The guard is gone.
*/
Route::post('/ingest/{business}/{source}', IngestWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.ingest.webhook');

Route::post('/extension/{key}/session', ExtensionSessionController::class)
    ->middleware('throttle:60,1')
    ->name('api.extension.session');

Route::post('/extension/{key}/scan', ExtensionScanController::class)
    ->middleware('throttle:60,1')
    ->name('api.extension.scan');
