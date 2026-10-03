<?php

use App\Exceptions\TenantNotResolved;
use App\Http\Middleware\EnforcesStaffSessionLifetime;
use App\Http\Middleware\Impersonating;
use App\Http\Middleware\RequiresTwoFactor;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SuspendedTenantStatus;
use App\Http\Middleware\TenantRole;
use App\Http\Middleware\WatchPlatformHeartbeats;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['tenant.role' => TenantRole::class]);
        // ⛔ GLOBAL, AND IN THE WEB PROCESS ON PURPOSE (T176 P23). It watches
        // for the scheduler or the queue workers going silent, and silence is
        // exactly what neither of them can report about itself. The web process
        // fails independently of both, so it is the one that can notice.
        //
        // Global rather than appended to `web` and `api` separately, because the
        // requests most likely to still be arriving when everything else has
        // stopped are the carrier and gateway webhooks, and those are the ones
        // an operator would least like to have excluded by a group boundary.
        //
        // ⚠️ IT COSTS ONE CACHE READ PER REQUEST AND RUNS AFTER THE RESPONSE.
        // The work is in `terminate()`, throttled to one sweep a minute, and
        // catches everything: nothing about alerting may reach a request (R25).
        $middleware->append(WatchPlatformHeartbeats::class);

        $middleware->web(append: [
            // `28` §9.1's session lifetime — 12h absolute, 30m idle — on the
            // whole group for RequiresTwoFactor's reason, and ahead of
            // everything below it because an expired session must not establish
            // a tenant, must not have a support session re-validated against
            // it, and must not reach route model binding. Each of those runs a
            // query for a request that ended before it started.
            EnforcesStaffSessionLifetime::class,
            ResolveTenant::class,
            // After ResolveTenant, and on the *whole* web group rather than on
            // the tenant screens. A support session must be re-validated, and
            // its banner must render, on every page an agent can reach while
            // one is open — which includes the tenant's own public feedback
            // page, the one route in this application where a GET writes
            // (decision 387). Scoping this to an authenticated group would
            // leave exactly that route unguarded.
            Impersonating::class,
            // `28` §9.1's mandatory 2FA for internal accounts. On the whole
            // group rather than on /admin and /support, for the reason above it
            // — a rule scoped to today's two console prefixes is a different
            // and weaker rule, and the third prefix is one route-file edit away
            // from somebody who has no reason to think about this middleware.
            // See RequiresTwoFactor for the exemption list that keeps the
            // enrolment screen reachable.
            RequiresTwoFactor::class,
            // `28` §9.5's "login shows a plain status page", on the whole web
            // group for the two above it: a rule scoped to today's owner
            // surfaces is a different and weaker rule, and it must not depend
            // on which of the four sign-in routes was used (661). Last of the
            // four, so an expired session, an impersonation re-validation and a
            // missing second factor are all settled before it can redirect.
            SuspendedTenantStatus::class,
        ]);

        // The API path needs the tenant established exactly as much as the web
        // path does — every model it touches is scoped, and without this an
        // authenticated token request fails closed on its first query.
        //
        // ResolveTenant reads Auth::id(), which is populated for a token request
        // because Illuminate's Authenticate middleware calls shouldUse() on the
        // guard it matched. The priority list below is what guarantees it has
        // already run.
        $middleware->api(append: [
            ResolveTenant::class,
        ]);

        // ⚠️ THE STRIPE WEBHOOK CANNOT CARRY A CSRF TOKEN, AND DOES NOT NEED ONE.
        // Stripe posts from its own servers with no session and no cookie, so
        // every delivery would be rejected without this. What replaces the token
        // is stronger: the whole body is HMAC-signed with a shared secret and
        // checked inside a five-minute window by StripeWebhooks::verify(), which
        // refuses a forged post whether or not it arrives with a valid session —
        // something a CSRF token cannot do.
        //
        // Named as an exact path rather than a `webhooks/*` wildcard. There is
        // one webhook today and a wildcard would silently exempt the next one,
        // whoever adds it and whatever it does.
        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',

            // Row 4 slice 2's inbound SMS. Named individually rather than by a
            // `webhooks/*` wildcard, on the reasoning the comment above already
            // gives: a wildcard would silently exempt the next endpoint anybody
            // adds under that prefix, and the next one after this handles
            // delivery receipts.
            //
            // ⚠️ WHAT REPLACES THE TOKEN IS HMAC-SHA256 OVER THE RAW BODY, AND
            // IT FAILS CLOSED. `InfobipWebhookVerifier` refuses every request
            // when no signing key is configured — including genuine ones — so
            // this exemption cannot leave an open endpoint behind it. The
            // symptom of a missing key is an endpoint that does nothing, which
            // is the failure worth having on the path that can suppress any
            // phone number on the platform.
            'webhooks/infobip/inbound',

            // Row 4 slice 3's delivery receipts, on the same signing key and
            // the same fail-closed verifier. Named individually for the reason
            // the inbound entry gives — this IS "the next one after this".
            'webhooks/infobip/delivery',

            // T176 P2 — the Calls event webhook, on the same signing key and the
            // same fail-closed verifier. Named individually for the reason the
            // inbound entry gives.
            //
            // ⚠️ **THE SIGNATURE IS WORTH MORE THAN IT LOOKS AND LESS THAN ON
            // THE INBOUND PATH.** This endpoint accepts a vendor call id and
            // nothing else — every fact about the call is read back from Infobip
            // under our own credential — so a forgery cannot inject a phone
            // number, a tenant or an outcome the way a forged `results[]` can
            // suppress any number on the platform. What it could do is spend
            // vendor reads and replay a real call id, and the signature is what
            // stops the first while `VoiceCalls`' settled-outcome check makes the
            // second inert.
            'webhooks/infobip/voice',

            // Row 3 slice I — Zernio review/account push. HMAC-SHA256 over the
            // raw body (`X-Zernio-Signature`), fail closed when unconfigured.
            'webhooks/zernio',

            // The SES bounce and complaint feed (open question H, decisions
            // 2069 and 2094). Named individually for the reason above.
            //
            // ⚠️ WHAT REPLACES THE TOKEN IS NOT AN HMAC, AND THAT IS THE ONE
            // THING TO KNOW BEFORE COPYING THE ENTRIES ABOVE. SNS shares no
            // secret with us: it signs with a private key and names the
            // matching certificate inside the message. `SnsMessageVerifier`
            // constrains that URL's host before fetching it and checks the
            // TopicArn against an allowlist — and it fails closed on an empty
            // allowlist, which is the state a fresh install is in, so this
            // exemption cannot leave an open endpoint behind it either.
            'webhooks/ses',

            // Cloud Pub/Sub's Gmail push (T137 §3 rail 3), the inbound half of
            // the transport 2093 made primary.
            //
            // ⚠️ A THIRD SHAPE, AND IT IS NOT THE SNS ENTRY ABOVE WITH A
            // DIFFERENT VENDOR NAME. Pub/Sub signs **nothing in the request** —
            // no HMAC over the body, no signature over a field list. What
            // arrives is an OpenID Connect JWT in the `Authorization` header
            // naming the calling service account, so a verified request proves
            // *who called* and nothing at all about *what they said*.
            // `GooglePushTokenVerifier` checks the signature, the `email` claim
            // against the one account we accept and the `aud` against this
            // endpoint's URL, and fails closed when either is unconfigured — so
            // this exemption cannot leave an open endpoint behind it.
            //
            // ⛔ AND THE CONTROLLER TREATS THE BODY AS UNTRUSTED ANYWAY. It
            // reads the mail back from Gmail over our own connection rather
            // than acting on the payload, which is what makes an unsigned body
            // acceptable here and would not make one acceptable above.
            'webhooks/gmail',

            // The RFC 8058 one-click unsubscribe (T176 P21).
            //
            // ⚠️ THE FIRST WILDCARD IN THIS LIST, AND IT MATCHES A TOKEN RATHER
            // THAN A FAMILY OF ENDPOINTS. Every entry above is an exact path,
            // deliberately, so that the next endpoint under a prefix is not
            // silently exempted. That reasoning is kept: this pattern is
            // `mail/unsubscribe/*` and not `mail/*`, so it covers exactly one
            // route with one variable segment. Anything else added under `mail/`
            // still carries the token.
            //
            // ⛔ AND WHAT REPLACES THE TOKEN IS NOT A SIGNATURE OVER A REQUEST —
            // IT IS THE REQUEST. RFC 8058 §3.1 has a mail client post this
            // unattended, with no session and nothing to hold a CSRF token in;
            // requiring one would refuse every genuine one-click unsubscribe,
            // and CAN-SPAM §7704(a)(3)(A) separately forbids requiring anything
            // of the recipient beyond sending the reply. The URL's sealed claim
            // is authenticated with `APP_KEY` — a forged one fails the MAC — and
            // ⚠️ **THE ONLY THING A HOLDER OF ONE CAN DO IS SUPPRESS THE ADDRESS
            // IT NAMES**, which is why a CSRF-shaped attack on it has no prize:
            // the endpoint reads nothing back and answers identically whether or
            // not anything was written.
            'mail/unsubscribe/*',

            // The second card gateway (2056, T137 R2/SL-11). HMAC-SHA512 over
            // the raw body (`X-ANET-Signature`), fail closed when unconfigured.
            //
            // ⚠️ AND THIS ONE HAS NO REPLAY WINDOW, WHICH THE STRIPE ENTRY ABOVE
            // RELIES ON. Stripe signs `timestamp.payload` and its SDK enforces a
            // five-minute tolerance; Authorize.Net signs the body alone, so a
            // captured notification replays forever. What refuses a replay here
            // is the unique `notification_id` claim row, not the signature —
            // which is why `AuthorizeNetEvent` refuses deletes in stronger terms
            // than `StripeEvent` does.
            'webhooks/authorize-net',
        ]);

        // Sanctum's SPA support, so one guard covers both credentials: a bearer
        // token for the widget and headless clients, and the ordinary session
        // cookie for a first-party browser request. Stateful domains come from
        // config/sanctum.php.
        $middleware->statefulApi();

        // The position matters twice over, and the two constraints pull in
        // opposite directions:
        //
        //   after  StartSession       — Auth::id() is null before the session
        //                               exists, so an earlier ResolveTenant
        //                               would silently never resolve anything
        //   before SubstituteBindings — route model binding resolves
        //                               tenant-scoped models, and binding one
        //                               before the tenant exists throws
        //
        // Neither prepend nor append satisfies both: prepending puts it ahead of
        // StartSession, appending puts it behind SubstituteBindings. Laravel's
        // priority list is the mechanism for "this runs before that", so the
        // default list is restated here with ResolveTenant slotted between them.
        //
        // Restating it means an upgrade that changes Laravel's own list will not
        // reach us. A test asserts the two orderings that actually matter, so
        // that shows up as a failure rather than as silence.
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,
            // ⚠️ BEFORE ResolveTenant, and the opposite choice to
            // RequiresTwoFactor's below — the two are not in tension. Decision
            // 666 put 2FA behind Authorize so a refused screen answers with the
            // 403 that is true; that is a question about a session which is
            // legitimately open. This one is whether a session is open at all,
            // and the answer has to arrive before anything spends a query on
            // the request.
            //
            // It does not repeat 666's second consequence: this redirects only
            // a session that has actually expired, and no authorization test in
            // this suite advances a clock, so the twelve `403` assertions that
            // decision protects keep asserting authorization.
            EnforcesStaffSessionLifetime::class,
            ResolveTenant::class,
            // Immediately after ResolveTenant and still ahead of
            // SubstituteBindings, for both of the reasons above. It has to see
            // the tenant ResolveTenant established in order to overrule it, and
            // it has to refuse a view-only write *before* route model binding
            // resolves anything — a bound model on a refused request is a
            // tenant-scoped query run for a request that was never allowed to
            // happen.
            Impersonating::class,
            SubstituteBindings::class,
            Authorize::class,
            // ⚠️ AFTER Authorize, and the ordering is a real choice rather than
            // a leftover. Both orders are safe — neither lets an unenrolled
            // staff account reach a screen — but they answer a different person
            // differently. Ahead of Authorize, a `cs_readonly` refused from the
            // plan-price editor is told to go and set up 2FA, which implies
            // that would get them in; it never would. Behind it, they get the
            // 403 that is true, and the enrolment redirect is shown only to
            // somebody it would actually help.
            //
            // It also keeps the authorization assertions in this suite pointed
            // at authorization. Ahead of Authorize, twelve existing `403` tests
            // start asserting `302` and quietly stop covering the boundary they
            // were written for — decisions 574 and 630 are both about
            // assertions that were real and no longer the one that would fail.
            //
            // Impersonating still runs before it either way, so an impersonated
            // request has already been re-validated and had its read-only flag
            // settled by the time this can redirect.
            RequiresTwoFactor::class,
            // ⚠️ AFTER Authorize TOO, for decision 666's reason applied a second
            // time, and the case that settles it is decision 621's staff member
            // who also owns a business. Ahead of Authorize, their own suspended
            // account would answer `/admin` with a hold page — implying the
            // suspension is why they cannot get in, when the true answer might
            // be their role. Behind it, whichever refusal is real is the one
            // they get.
            //
            // Last, so an expired session, an impersonation re-validation and a
            // missing second factor are all settled before this can redirect.
            // It also keeps this suite's authorization assertions pointed at
            // authorization: no existing test suspends a business, so none of
            // the `403` assertions decision 666 protects can start asserting
            // `302` because of this line.
            SuspendedTenantStatus::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ⛔ THE `api/*` HALF IS THE DAY-ONE SCAFFOLD AND IS DELIBERATELY
        // UNCHANGED (9975). `git log -S "shouldRenderJsonWhen"` on this file
        // returns exactly one commit — `9c777b80`, the Laravel 13 scaffold,
        // whose `bootstrap/app.php` is 20 lines long and already carries this
        // verbatim. No decision argued for it, no comment explained it, no test
        // asserted it. **So there was no author to ask**, and widening it to
        // `expectsJson()` would be re-deciding, on behalf of every route in the
        // application, something nobody had decided. Measured as a mutation:
        // that widening turns `POST /register` and `POST /login` from `302`
        // into `422` for any JSON caller — four auth doors two waves measured
        // (9620-9637, 9880-9894).
        //
        // ⛔ THE SECOND CLAUSE EXISTS BECAUSE LIVEWIRE'S OWN JAVASCRIPT BRANCHES
        // ON THE LITERAL `422` AND THIS APPLICATION COULD NOT ANSWER ONE (9976).
        // `FileUploadController::validateAndStore()` calls `->validate()` from a
        // CONTROLLER, so an upload this application refuses raised a
        // `ValidationException` that the clause above rendered as a `302` and an
        // HTML redirect. Measured end to end on a running server rather than
        // read: `302` -> `Location: /account/customers/import` -> `200
        // text/html`, 25,716 bytes -> `JSON.parse()` throws. XHR follows a
        // redirect transparently, so `request.status` is `200`,
        // `livewire.js:808`'s success branch is taken, and the `SyntaxError`
        // lands inside the `load` listener — **neither `_finishUpload` nor
        // `_uploadErrored` ever fires**, and the upload stalls with no progress,
        // no error and no word to the person who picked the file.
        //
        // ⚠️ KEYED ON THE ROUTE NAME AND DELIBERATELY NOT ON THE PATH (9978).
        // `EndpointResolver::prefix()` hashes `APP_KEY`, so the URI is
        // `/livewire-<8 hex>/upload-file` and DIFFERS PER INSTALL — this
        // worktree serves `livewire-165a34f7` while four docblocks in `app/`
        // record `livewire-16868c99` from another checkout. A path literal would
        // be right here and wrong in production, and would stop matching the
        // moment `APP_KEY` were rotated, silently restoring the stall.
        //
        // ⚠️ AND IT SURVIVES `route:cache`, WHICH IS THE ONE THING A GREEN SUITE
        // COULD NOT SAY (9990). `composer deploy` runs `route:cache`, and 9624
        // records routes registered outside a routes file being invisible to a
        // cached table. Livewire registers this one from `SupportFileUploads`,
        // so it was measured rather than assumed: with
        // `bootstrap/cache/routes-v7.php` in place (190 routes),
        // `livewire.upload-file` is in the table and a real 3 MB upload against
        // the cached table answered `422`.
        //
        // ⚠️ ONE VENDOR ROUTE, AND THE BLAST RADIUS IS PINNED BY A TEST rather
        // than by this comment —
        // `tests/Feature/Uploads/LivewireUploadValidationTest.php`. Livewire's
        // main update endpoint is NOT included and needs nothing: it sends no
        // `Accept` header, and `SupportValidation` catches `ValidationException`
        // itself.
        //
        // ⚠️ `routeIs()` IS NULL-SAFE AND THAT MATTERS HERE. `ValidatePostSize`
        // is global middleware and throws BEFORE routing, so a body over
        // `post_max_size` still renders its `413` as HTML — which is what the
        // JavaScript wants anyway, because any non-2xx reaches
        // `_uploadErrored`.
        // ⛔ A SIGNED-IN ACCOUNT WITH NO BUSINESS THAT OPENS A TENANT SCREEN GETS THE 403 PAGE, NOT A 500 (owner,
        // 2026-10-02: "tenantless screens 500"). The convention is SetupController's: "every other tenant-only surface
        // in this application answers a tenantless request the same way" — a 403 — and there is no business picker to
        // redirect to, because membership is one business per person (ResolveTenant). ~72 screens reached the tenant
        // first in mount() or render() and threw TenantNotResolved instead, which rendered as "Something went wrong on
        // our end". Only a browser request from a signed-in user is answered here: a JSON or api/* caller, a job and a
        // console command keep the exception exactly as before, and it is still reported, because reaching tenant data
        // with no tenant is worth knowing about.
        $exceptions->render(function (TenantNotResolved $e, Request $request) {
            if ($request->user() === null || $request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            return response()->view('errors.403', [], 403);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->routeIs('livewire.upload-file'),
        );
    })->create();
