<?php

declare(strict_types=1);

use App\Http\Controllers\Actuation\T3InjectionController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PixelIngestController;
use App\Http\Controllers\Api\PlaceSuggestionController;
use App\Http\Controllers\Api\PublicAuditController;
use App\Http\Controllers\Api\WidgetReviewController;
use App\Http\Middleware\ResolveWidget;
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
