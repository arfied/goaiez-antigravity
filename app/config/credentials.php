<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Platform credentials — bootstrap seeds only
|--------------------------------------------------------------------------
|
| Doc `38` D-149: "environment variables are bootstrap seeds only. At runtime,
| every vendor credential resolves from the encrypted `platform_credentials`
| store." That store is CFG1 and is not built (BUILD-PLAN §4.4), so this file is
| where the seeds sit until it is.
|
| NOTHING SHOULD READ THIS FILE DIRECTLY. Read through App\Support\
| PlatformCredentials, which is the seam CFG1 replaces the inside of. A call site
| that reaches for config('credentials.…') is the precedent CFG1 has to undo.
|
| These are credentials that are OURS — one key, no tenant. A tenant's own OAuth
| tokens live encrypted per-connection behind TokenService and never appear here.
|
*/

return [

    /*
    | Google Places API (New). Used by the free instant audit, which runs before
    | any tenant exists, so it cannot come from a tenant's OAuth connection.
    |
    | Restrict this key to the Places API and to server IPs in the Cloud
    | console. Decision 195 keeps it server-side — autocomplete is proxied and
    | debounced rather than called from the browser, so the key is never shipped
    | to a visitor and the spend stays inside the daily budget.
    */
    'google_places_key' => env('GOOGLE_PLACES_API_KEY'),

    /*
    | Cloudflare Turnstile (decision 194). Guards the free audit endpoint after
    | a visitor's first run — `29` §6.2: "rate limit 3/hr/IP + captcha after 1".
    |
    | The site key is public by design; it is rendered into the page. The secret
    | is not, and is the half that makes siteverify mean anything.
    |
    | Locally and in CI, use Cloudflare's published dummy keys — sitekey
    | 1x00000000000000000000AA and secret 1x0000000000000000000000000000000AA
    | both always pass. They must be paired: a dummy sitekey with a production
    | secret fails, which is a confusing way to lose an afternoon.
    */
    'turnstile_site_key' => env('TURNSTILE_SITE_KEY'),

    'turnstile_secret' => env('TURNSTILE_SECRET'),

    /*
    | AI providers. Both wired from day one — `CLAUDE.md`'s stack section makes
    | multi-provider a deliberate override of the archived `20` §5's
    | OpenAI-default router, because "which model serves which task" is a setting
    | an operator changes when a price moves or a model is retired, and a router
    | with one provider wired cannot honour that setting without a deploy.
    |
    | Neither is required for the app to boot. A missing key degrades the tasks
    | that use that provider — AiClient returns a failed response and the caller,
    | always a queued job, records it and moves on.
    */
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    'openai_api_key' => env('OPENAI_API_KEY'),

    'xai_api_key' => env('XAI_API_KEY'),

    'gemini_api_key' => env('GEMINI_API_KEY'),

    /*
    | Infobip — numbers, SMS/10DLC, WhatsApp, voice, and brand registration.
    | `CLAUDE.md` names it the single vendor for all of those; email is
    | deliberately NOT among them (Microsoft 365, then Azure Communication
    | Services).
    |
    | ✅ THIS NOW HAS A READER, AND IT ARRIVED THROUGH THE SEAM THIS ENTRY WAS
    | WRITTEN TO PROTECT. App\Services\Sms\InfobipClient (row 4 slice 1) asks
    | PlatformCredentials::get('infobip_api_key') and never config() — which is
    | exactly what the paragraph this replaces predicted would otherwise go
    | wrong, and it is worth recording that paying for the seam before there was
    | a caller cost nothing and was honoured. The webhooks are still absent:
    | STOP/HELP is slice 2 and delivery receipts are slice 3.
    |
    | ⚠️ A READER IS NOT A SEND. The client is one of two drivers, SMS_DRIVER
    | seeds `log`, and PlatformTexter refuses every send while `sms.enabled` is
    | false — which waits on slice 2 (1567).
    |
    | The base URL is NOT here. Infobip issues an account-specific host
    | (`xxxxx.api-us.infobip.com`) which is configuration rather than a secret, so
    | it sits in config/services.php — the same split TurnstileVerifier already
    | uses for its timeout.
    |
    | ⚠️ Build the client on Laravel's HTTP client, never Infobip's PHP SDK
    | (decision 277's reasoning, applied one vendor over): Http::
    | preventStrayRequests() and `40` Part 8's outbound lint both see through
    | Http::, and neither would see an SDK's own bundled Guzzle.
    */
    'infobip_api_key' => env('INFOBIP_API_KEY'),

    /*
    | The HMAC signing key for INBOUND webhooks (row 4 slice 2). A different
    | secret from the API key above and in the opposite direction: that one
    | authenticates us to Infobip, this one authenticates Infobip to us.
    |
    | ⚠️ WITHOUT IT THE INBOUND ENDPOINT REFUSES EVERYTHING, INCLUDING GENUINE
    | DELIVERIES, and that is the design rather than an oversight. Skipping
    | verification when unconfigured would leave an unauthenticated endpoint
    | that can suppress any phone number on the platform — the permissive branch
    | applied exactly where the strict rule was meant to bind.
    |
    | ⚠️ HMAC IS OPT-IN ON THE NOTIFICATION PROFILE. Infobip offers Basic auth,
    | HMAC, OAuth 2.0 and mTLS, and none is on by default, so this key does not
    | exist until somebody configures HMAC in the Infobip console. The header
    | name it signs with is account-specific and lives in config/services.php.
    */
    'infobip_webhook_secret' => env('INFOBIP_WEBHOOK_SECRET'),

    /*
    | Zernio — Google Business Profile, read through an intermediary while our
    | own GBP API application clears.
    |
    | ⚠️ THIS KEY IS OURS; THE GOOGLE AUTHORISATION BEHIND IT IS THE TENANT'S.
    | That split is the whole reason the integration exists and the whole reason
    | it is temporary. Zernio holds an approved Google Cloud project, so a tenant
    | OAuths into *their* app and we read reviews today rather than at the end of
    | an approval with no published SLA. `GoogleBusinessService`'s docblock has
    | the terms we are waiting on: 0 QPM until approved, no sandbox.
    |
    | The consequence to keep in view: one API key reads every tenant's reviews,
    | so a leak here is not scoped to one business the way a tenant OAuth token
    | is. Restrict it to server IPs if Zernio ever offers that, and rotate it on
    | any suspicion rather than on a schedule.
    |
    | Format is `sk_` + 64 hex characters. Zernio stores only a SHA-256 hash and
    | shows the key once at creation — there is no "view key" screen to go back
    | to, so a lost key is a rotated key.
    */
    'zernio_api_key' => env('ZERNIO_API_KEY'),

    /*
    | HMAC secret for inbound Zernio webhooks (row 3 slice I). Lowercase hex
    | HMAC-SHA256 of the raw body, header `X-Zernio-Signature` — verified against
    | Zernio's live docs on 2026-08-10. Fail closed when unset.
    */
    'zernio_webhook_secret' => env('ZERNIO_WEBHOOK_SECRET'),

    /*
    | Google Indexing API service account (row 9 slice E). The whole service
    | account JSON key, encrypted at rest like every other row here.
    |
    | ⛔ UNSET ON EVERY DEPLOYMENT, AND THREE THINGS BESIDES THIS WOULD HAVE TO
    | BE TRUE BEFORE IT COULD DO ANYTHING. The account must be added as a *site
    | owner* of the property in Search Console; Google must grant approval and
    | quota — the docs describe "a default 200 quota for API onboarding and
    | submission testing"; and there must be a page of one of the two kinds the
    | API accepts, which no table in this schema can hold today.
    |
    | ⚠️ THE REQUEST-MAKING HALF IS NOT BUILT. `App\Services\Indexing\IndexingApi`
    | runs Google's content restriction as a gate and refuses; this key is what
    | it asks for after the gate, so that the gate can be driven with the
    | credential arm green rather than passing because something upstream said no
    | first (398).
    */
    'google_indexing_service_account' => env('GOOGLE_INDEXING_SERVICE_ACCOUNT'),

    /*
    | Google Workspace — the internal OAuth app that carries platform mail
    | (2093, T137 R3), and reads the relay mailbox for replies (T137 §3 rail 3).
    |
    | ⛔ THESE THREE KEYS WERE ASKED FOR BY `GmailApiClient` AND DECLARED
    | NOWHERE, WHICH MADE THE PRIMARY SEND PATH THROW BEFORE IT REACHED GOOGLE.
    | `PlatformCredentials::get()` answers only for keys `CredentialManifest`
    | names — deliberately, because an undeclared key is a typo far more often
    | than a new vendor — so `accessToken()` raised *"`gmail_client_id` is not
    | declared in CredentialManifest"* on the first send, whether or not the
    | credentials existed. It went unnoticed because 2441 records that the
    | transport is covered only by `Http::fake()` expectations and **nothing had
    | ever driven `accessToken()`**; the inbound half needs the same token, which
    | is how it surfaced. The gap is the exact one the manifest/config lint was
    | written for after `zernio_api_key` (see `CredentialManifest`) — two
    | branches, neither wrong alone, and the hole only in the merge.
    |
    | ⚠️ THE GRANT IS THE PLATFORM'S OWN AND NEVER A TENANT'S (2072). A tenant's
    | connection to a tenant's mailbox lives in the vault; this is our Workspace
    | account, used where no tenant is resolved.
    |
    | ⚠️ ONE REFRESH TOKEN, TWO SCOPES. `gmail.send` authorises the send;
    | `users.watch`, `users.history.list` and `users.messages.get` list four
    | authorising scopes and `gmail.send` is on none of them, so the internal app
    | must additionally be granted `gmail.metadata` — headers, never bodies.
    | Verified against Google's own reference on 2026-08-12.
    */
    'gmail_client_id' => env('GMAIL_CLIENT_ID'),
    'gmail_client_secret' => env('GMAIL_CLIENT_SECRET'),
    'gmail_refresh_token' => env('GMAIL_REFRESH_TOKEN'),

    /*
    | ⛔ A SECOND WORKSPACE ACCOUNT AND A SECOND TOKEN — T176 P24, the support
    | mailbox. The paragraph above says "one refresh token, two scopes" and that
    | is still true *of the relay account*; this is a different account, granted
    | `gmail.readonly` because the support desk needs a message body and
    | `gmail.metadata` is documented as unable to fetch one. Two accounts is what
    | keeps the relay's guarantee true, so ⚠️ **THE SAME TOKEN IN BOTH FIELDS
    | DEFEATS IT** and nothing in code can tell.
    |
    | The client id and secret above are shared: one internal OAuth app, two
    | authorised accounts.
    */
    'gmail_support_refresh_token' => env('GMAIL_SUPPORT_REFRESH_TOKEN'),

    /*
    | Stripe — billing (row 22 slice B). Two keys, and they fail in opposite
    | directions, which is why neither may stand in for the other.
    |
    | The SECRET signs our outbound calls: creating a customer, opening a
    | Checkout Session. Missing, nobody can subscribe and the failure is loud and
    | immediate at the moment somebody tries.
    |
    | ⚠️ The WEBHOOK SECRET verifies Stripe's inbound posts, and missing it is
    | the quiet one. `StripeWebhooks` refuses every event it cannot verify, so an
    | unset secret means the endpoint answers Stripe with a failure, Stripe
    | retries for three days and gives up, and the visible symptom is
    | subscriptions that never leave `pending_checkout` while Checkout itself
    | works perfectly. **Fail closed is still right** — an endpoint that accepts
    | unverified billing events is an endpoint anybody can post a paid
    | subscription to — but the state it fails into looks like a Checkout bug and
    | is not one.
    |
    | Both are per-mode: a test-mode secret produces `sk_test_…` and its own
    | webhook signing secret, and the two must be from the same mode or every
    | signature check fails against traffic that verifies fine elsewhere.
    |
    | ⚠️ There is no publishable key here and that is not an omission. Hosted
    | Checkout redirects the browser to Stripe's own domain, so no key is
    | rendered into any page of ours; card data never touches this application
    | (`cashier-billing` skill). A publishable key would arrive with Elements,
    | which this slice deliberately does not build.
    */
    'stripe_secret' => env('STRIPE_SECRET'),

    'stripe_webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    /*
    | Authorize.Net — the primary card gateway (decision 2056, T137 R2). Four
    | keys, and one of them is deliberately public.
    |
    | ⚠️ THE PUBLIC CLIENT KEY IS RENDERED INTO A PAGE, ON PURPOSE, AND IT IS
    | THE ONE THING HERE THAT IS NOT A SECRET. Stripe's hosted Checkout needs no
    | key in our HTML because the browser leaves for Stripe's own domain;
    | Accept.js does the opposite — the card fields are on our page and the
    | tokenisation happens in the browser, so the API login id and the public
    | client key both go into the markup. The vendor's own documentation says
    | so: "you cannot use the Public Client Key to initiate a transaction, you
    | may safely store the Public Client Key in a website or smartphone
    | application" (read 2026-08-11). **The transaction key must never appear in
    | a page**, and it is a different value entirely.
    |
    | ⚠️ SAQ-A IS PRESERVED BECAUSE THE CARD NEVER REACHES OUR SERVER, NOT
    | BECAUSE IT NEVER REACHES OUR PAGE. Accept.js posts the card straight to
    | Authorize.Net and hands the page back an opaque nonce; the form we submit
    | carries that nonce and no card field. Decision 2056: "no card number
    | touches this application" on either path.
    |
    | ⚠️ THE SIGNATURE KEY IS NOT THE TRANSACTION KEY AND MIXING THEM FAILS
    | SILENTLY IN ONE DIRECTION. It verifies inbound webhooks (HMAC-SHA512 over
    | the raw body, header `X-ANET-Signature`); the transaction key authenticates
    | our outbound calls. Both live under Account > Settings > Security Settings
    | > General Security Settings > API Credentials and Keys, next to each other,
    | which is exactly how they get swapped. A wrong signature key refuses every
    | webhook — the same quiet failure the Stripe note above describes.
    |
    | No base URL here: the two hosts are literals in `AuthorizeNetApi`,
    | deliberately, so the outbound-host lint can read them (428–433, 438). The
    | sandbox/production switch is in `config/services.php` because it selects
    | between two literals rather than supplying one.
    */
    'authorize_net_api_login_id' => env('AUTHORIZE_NET_API_LOGIN_ID', env('AUTHORIZENET_API_LOGIN_ID')),

    'authorize_net_transaction_key' => env('AUTHORIZE_NET_TRANSACTION_KEY', env('AUTHORIZENET_TRANSACTION_KEY')),

    'authorize_net_signature_key' => env('AUTHORIZE_NET_SIGNATURE_KEY', env('AUTHORIZENET_SIGNATURE_KEY')),

    'authorize_net_public_client_key' => env('AUTHORIZE_NET_PUBLIC_CLIENT_KEY', env('AUTHORIZENET_PUBLIC_CLIENT_KEY')),

    /*
    | Web Push VAPID keys for browser notifications.
    | The public key is shown to browsers and the private key never leaves the server.
    | Generated by `php artisan push:vapid-keys`, rotating it invalidates every browser subscription.
    */
    'webpush_vapid_public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
    'webpush_vapid_private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),

];
