<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile
    |--------------------------------------------------------------------------
    |
    | The keys are credentials and live in config/credentials.php. This is the
    | rest — and it existed only as an inline default until now: TurnstileVerifier
    | has always read `config('services.turnstile.timeout', 5)` against a
    | `turnstile` key that was never in this file, so the value was unreachable
    | and anybody setting it would have found it silently ignored.
    |
    | Behaviour is unchanged: the default was 5 and still is.
    |
    */

    'turnstile' => [
        // Seconds. Short on purpose — siteverify sits on the synchronous path of
        // a public audit request, and TurnstileVerifier fails closed on a
        // timeout, so a slow Cloudflare refuses audits rather than hanging them.
        'timeout' => (int) env('TURNSTILE_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Infobip — numbers, SMS/10DLC, WhatsApp, voice, brand registration
    |--------------------------------------------------------------------------
    |
    | ⚠️ THE API KEY IS NOT HERE. It reads through App\Support\
    | PlatformCredentials from config/credentials.php, which is the seam CFG1
    | replaces the inside of (BUILD-PLAN §4.4). What lives here is the part that
    | is configuration rather than a secret.
    |
    | ⚠️ `base_url` HAS NO DEFAULT ON PURPOSE. Infobip issues each account its
    | own host — `xxxxx.api-us.infobip.com`, region segment and all — and there
    | is no sensible fallback: a
    | guessed host either fails to resolve or, worse, resolves to somebody else's
    | account boundary. A missing value must be a loud failure when the client is
    | built, not a quiet request to the wrong place.
    |
    | ✅ THESE NOW HAVE A READER — App\Services\Sms\InfobipClient, row 4 slice 1
    | (BUILD-PLAN §2.10.3). This block said "NOTHING READS THIS YET" from the day
    | the keys landed until 2026-08-09, which was true and is not any more. The
    | webhooks are still absent: STOP/HELP is slice 2 and delivery receipts are
    | slice 3.
    |
    | ⚠️ AND A READER IS NOT A SEND. The driver below seeds `log`, and even the
    | live driver is refused by PlatformTexter until `sms.enabled` is turned on —
    | which waits on slice 2, because you may not send what you cannot stop
    | (1567).
    |
    */

    'infobip' => [
        'base_url' => env('INFOBIP_BASE_URL'),

        // Seconds. Matches the AI clients' posture: a vendor that has stopped
        // answering must not hold a queue worker for the connection's default.
        'timeout' => (int) env('INFOBIP_TIMEOUT', 10),

        /*
         * The hosts an inbound MMS may be fetched from (T176 P10).
         *
         * ⛔ **AN ALLOWLIST, NOT A PATTERN, AND IT IS THE FIRST GATE THE
         * FETCHER APPLIES.** The media address arrives inside a webhook body.
         * The signature proves Infobip sent the body; it proves nothing about
         * where the URL inside it points, and a URL is the whole of an SSRF.
         * `InboundMediaFetcher` refuses anything not on this list *before a
         * socket opens*, which is the property its test asserts with
         * `Http::assertNothingSent()`.
         *
         * ⚠️ **`base_url`'s HOST IS ALWAYS INCLUDED AND IS NOT WRITTEN HERE.**
         * Infobip issues each account its own host, so there is no literal to
         * put in this file — the same fact that exempts `INFOBIP_BASE_URL` by
         * name from the outbound env-var assertion. The fetcher adds it, so a
         * deployment whose media rides the API host needs no configuration at
         * all.
         *
         * ⚠️ **AND THE EMPTY DEFAULT IS A REFUSAL RATHER THAN A GAP.** If
         * Infobip serves inbound media from a separate CDN host, that host has
         * to be read off a real delivery and put here — it is not guessable,
         * and `CLAUDE.md`'s rule is to verify a vendor string against the raw
         * artefact rather than against memory. Until then this fails closed:
         * the message still arrives, the media is recorded as refused, and
         * nothing is silently fetched from a host nobody checked (4168).
         *
         * ⛔ **THE DOCUMENTATION WAS READ AND IT DOES NOT ANSWER THIS** (4258,
         * 2026-08-16). Infobip's inbound MMS webhook reference describes the
         * field as *"URL from which content can be downloaded"* and every
         * example on the page is the placeholder
         * `https://examplelink.com/123456`:
         *
         *   https://www.infobip.com/docs/api/channels/mms/receive-mms/receive-inbound-mms-messages.md
         *
         * Nor is it derivable from the API surface — the whole *Receive MMS*
         * section is two pages, *Get inbound MMS messages* and this webhook, and
         * neither is a content-download endpoint. **So it remains empty, on
         * purpose, and this is what the owner has to obtain:**
         *
         *   1. **The host, from a real inbound MMS.** Send a picture to an
         *      Infobip number on the account, then read the `message[].url` of
         *      the delivered webhook body — from this application's own log, from
         *      Infobip's portal message logs, or from the *Get inbound MMS
         *      messages* pull API. **The hostname of that URL is the value.**
         *   2. **Whether it is stable**, from Infobip support: one host per
         *      account, one per region, or a rotating set. An allowlist is worth
         *      nothing against a host that changes weekly, and the answer decides
         *      whether this stays a config value or needs a different design.
         *   3. **Whether the download needs credentials**, likewise. Nothing here
         *      sends an `Authorization` header, so if the CDN requires one every
         *      fetch records `refused_unreachable` — which is at least loud.
         *
         * Comma-separated, e.g. `INFOBIP_MEDIA_HOSTS=cdn.example-account.com`.
         */
        'media_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('INFOBIP_MEDIA_HOSTS', '')),
        ))),

        /*
         * The number a message is sent FROM.
         *
         * ⚠️ NO DEFAULT, for base_url's reason one step further on: a message
         * sent from a number the 10DLC campaign was not registered against is
         * not refused by the carrier, it is *filtered* — which looks exactly
         * like delivery from our side and reaches nobody.
         *
         * ⚠️ ONE SENDER, WHICH IS SLICE 1'S HONEST ANSWER. Number inventory,
         * warmup, quarantine and health-weighted rotation are slice 6 (doc
         * `51`); until that table exists there is nothing to rotate between,
         * and a picker over a table that does not exist is the writerless shape
         * this codebase has recorded fifteen times.
         */
        'sender' => env('INFOBIP_SENDER'),

        /*
         * The header an inbound webhook carries its signature in.
         *
         * ⚠️ CONFIGURATION RATHER THAN A CONSTANT, AND THIS IS THE ONE THING
         * ABOUT INFOBIP'S WEBHOOKS THAT A FROM-MEMORY IMPLEMENTATION GETS
         * WRONG. Stripe has `Stripe-Signature` and Twilio has
         * `X-Twilio-Signature`; Infobip's own documentation says "the exact
         * signing header name depends on your configuration. Verify the header
         * name and signing behavior in your Infobip account settings."
         *
         * A hardcoded guess would mean `$request->header()` returning null on
         * every genuine delivery. `InfobipWebhookVerifier::DEFAULT_HEADER` is
         * the value to start from and check, not one to trust — and it fails
         * closed, so being wrong costs an endpoint that refuses rather than one
         * that accepts.
         */
        'signature_header' => env('INFOBIP_WEBHOOK_SIGNATURE_HEADER'),

        /*
         * Which HMAC construction that profile signs with (4508).
         *
         * `body`     — HMAC over the raw request body, in the header above.
         * `exchange` — HMAC over `timestamp . body`, in the
         *              `X-Ib-Exchange-Req-*` headers of the Subscriptions family.
         * unset, or anything else — `auto`: whichever the headers describe.
         *
         * ⛔ AUTO IS NOT THE SAFE VALUE, IT IS THE ONE THAT CANNOT REFUSE A
         * GENUINE DELIVERY. Two constructions accepted on one endpoint means a
         * signature captured under one can be presented under the other's
         * domain — `InfobipWebhookVerifier`'s docblock has the exact replay.
         * Pinning this to the scheme the account actually uses is a step in the
         * voice activation runbook (4423), and it is the only thing that closes
         * it.
         */
        'signature_scheme' => env('INFOBIP_WEBHOOK_SIGNATURE_SCHEME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS transport — which driver carries a text message
    |--------------------------------------------------------------------------
    |
    | `log` (App\Services\Sms\LogTexter) reaches nobody and is the default
    | everywhere. `infobip` is the carrier. BUILD-PLAN §2.10.4: "the log driver
    | is what makes this row testable at all" — slices 2 through 6 all need
    | something that behaves like a send while row 4's 10DLC campaign sits
    | unfiled (1562).
    |
    | ⚠️ THIS IS THE DEPLOYMENT SWITCH AND NOT THE OPERATIONAL ONE, and the two
    | are deliberately separate. `sms.enabled` in the defaults registry is the
    | one an operator flips without a deploy, and it seeds false naming slice 2's
    | STOP handling as what it waits for (1567). Either one alone sends nothing.
    |
    | ⚠️ AN UNKNOWN VALUE IS A LOUD FAILURE AT BOOT, NEVER A FALLBACK TO `log`.
    | Falling back would mean a typo in INFOBIP/`inforbip` silently stops every
    | message in production while every log line, every test and every screen
    | reports a healthy send — this application's most-recorded failure shape,
    | on the channel where it is most expensive.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CMS actuation — which adapter writes to a tenant's own website
    |--------------------------------------------------------------------------
    |
    | `log` (App\Services\Actuation\LogCmsAdapter) reaches no website and is
    | the default everywhere. It is BUILD-PLAN §2.11.3 slice A's deliverable
    | rather than a gap: "nothing touches a real site in this slice".
    |
    | `wordpress` (App\Services\Actuation\WordPress\WordPressAdapter) arrived
    | with slice F1 and reaches a tenant's own site over WordPress core's REST
    | API with an owner-pasted Application Password. ⛔ THIS COMMENT SAID
    | "`wordpress` arrives with slice G" until 2026-08-19, which was the plan
    | before §2.11.5 conflict 7 split slice F (5580). What G still owns is the
    | ACTIVATION — `actuation.enabled`, the connect screen, and the staging
    | checklist against a real install — because write access to a stranger's
    | site without a PROVEN revert is the liability `29` rule 32 names, and F1
    | wrote the revert rather than proving it.
    |
    | ⛔ THIS COMMENT SAID `actuation.enabled` DOES NOT EXIST, AND IT WAS TRUE
    | WHEN F1 WROTE IT ON 2026-08-19 AND STOPPED BEING TRUE THE SAME DAY —
    | BOTH READINGS KEPT AND DATED. F1 verified zero occurrences outside
    | comments and said so rather than repeating the claim (314–316, and
    | `CLAUDE.md`'s own note on `IDENTITY_RESOLUTION_ENABLED`, the same
    | sentence about a different flag). Slice D then built the second switch,
    | which is what the paragraph below describes.
    |
    | ⚠️ THIS IS THE DEPLOYMENT SWITCH AND NOT THE OPERATIONAL ONE, exactly as
    | `SMS_DRIVER` is not `sms.enabled`. `actuation.enabled` is the registry row
    | slice D built (5665) and it seeds false. Either one alone changes nothing.
    |
    | ⛔ "AND THIS KEY IS THE STRONGER OF THE TWO TODAY … NOBODY CAN TURN ON AN
    | ADAPTER THAT DOES NOT EXIST" WAS TRUE AND IS NOT — CORRECTED 2026-08-20
    | (6127(b), 6184). It was already contradicted by the paragraph above, which
    | records `wordpress` arriving with F1 on 2026-08-19; `CMS_DRIVER=wordpress`
    | was then deployed to production for part of 2026-08-20 (5913). **A live
    | adapter exists and can be selected here in one line of `.env`.**
    |
    | ⚠️ WHAT SURVIVES IS THE MECHANISM AND NOT THE REASSURANCE.
    | `Publishing::canWriteToSite()` still asks three questions — the registry
    | row, the tier, and the adapter's own `health()` — and `LogCmsAdapter` still
    | answers "this deployment writes to no website" with an empty `snapshot()`,
    | so `SiteChanges::open()` refuses a change set on it whatever the registry
    | says. That is a property of the log driver, not of this deployment, and
    | **no comment in this repository can tell you which one is bound here.**
    | The Ops settings screen asks the container and says so at the moment of the
    | press (`Publishing::adapterReachesAWebsite()`).
    |
    | ⚠️ AN UNKNOWN VALUE IS A LOUD FAILURE AT BOOT, NEVER A FALLBACK TO `log`.
    | A typo that fell back here would leave every tenant's site silently
    | unactuated while every screen reported a healthy system — and, in the
    | other direction, it is the one config key whose mistake writes to somebody
    | else's property.
    |
    */

    'cms' => [
        'driver' => env('CMS_DRIVER', 'log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voice transport — which vendor answers a call (T176 P2, R7, R27(a))
    |--------------------------------------------------------------------------
    |
    | `null` (App\Services\Voice\NullVoiceProvider) reaches nobody and is the
    | default everywhere. `infobip` is the carrier.
    |
    | ⛔ THE EXTERNAL GATE LIVES ON THIS LINE. T176 §7 item 3: "Infobip —
    | account steps: activate Voice/Calls API (gates P2)". Nothing in this
    | repository can unblock it, so the whole voice path ships built, migrated,
    | routed, tested and inert — and activation is this variable plus the API
    | key that is already configured for SMS.
    |
    | ⚠️ THIS IS THE DEPLOYMENT SWITCH AND NOT THE OPERATIONAL ONE, exactly as
    | `SMS_DRIVER` is not `sms.enabled`. `voice.enabled` in the defaults
    | registry is the one an operator flips without a deploy, and it seeds
    | false. Either one alone answers nothing.
    |
    | ⚠️ AN UNKNOWN VALUE IS A LOUD FAILURE AT BOOT, NEVER A FALLBACK TO `null`.
    | Falling back would mean a typo silently stops every missed-call text-back
    | in production while every screen reports a healthy system — this
    | application's most-recorded failure shape, one channel over from where it
    | was already argued.
    |
    */

    'voice' => [
        'driver' => env('VOICE_DRIVER', 'null'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth providers (Socialite)
    |--------------------------------------------------------------------------
    |
    | Client credentials only. Endpoints and scopes live in config/oauth.php,
    | beside the documentation citations that justify them.
    |
    | `google` and `facebook` are Socialite's own driver keys. `microsoft` is
    | NOT — Socialite ships no Microsoft driver (Facebook, X, LinkedIn, Google,
    | GitHub, GitLab, Bitbucket and Slack are the built-ins, verified against
    | https://laravel.com/docs/13.x/socialite on 2026-07-31). The driver is
    | registered first-party in AppServiceProvider rather than by adding
    | socialiteproviders/microsoft, which is not an approved dependency.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', '/auth/microsoft/callback'),

        // 'common' admits both work/school and personal accounts. A
        // single-tenant deployment puts its directory id here instead.
        'tenant' => env('MICROSOFT_TENANT', 'common'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe — billing (row 22 slice B)
    |--------------------------------------------------------------------------
    |
    | Configuration only. Both Stripe secrets live in config/credentials.php and
    | are read through PlatformCredentials, which is where a vendor credential
    | belongs (`38` D-149) — the same split TurnstileVerifier already uses.
    |
    | No base URL here: it is a literal in StripeApi, deliberately, so the
    | outbound-host lint can read it (decisions 428–433, 438).
    |
    */

    'stripe' => [
        // ⚠️ LONGER THAN THE OTHER VENDORS' TEN SECONDS, ON PURPOSE. A person is
        // waiting on this call with a signup half finished, and a Checkout
        // Session create that takes twelve seconds and succeeds is a better
        // outcome for them than one abandoned at ten — the alternative is not a
        // faster answer, it is starting again. Nothing here runs on a queue.
        'timeout' => (int) env('STRIPE_TIMEOUT', 15),
    ],

    /*
    | Authorize.Net (decision 2056, T137 R2). Credentials are read through
    | PlatformCredentials; what lives here is the one switch that selects between
    | the two host literals in `AuthorizeNetApi`.
    |
    | ⚠️ SANDBOX UNLESS PRODUCTION IS SPELLED OUT, AND THE DEFAULT IS THE SAFE
    | DIRECTION. A deployment that forgets this variable charges nobody; the
    | inverse default would charge everybody. `AuthorizeNetApi` additionally
    | refuses to build a production URL at all under the test runner, because on
    | this vendor a stray production call is a real charge against a live
    | merchant account — and `Http::preventStrayRequests()` cannot help there,
    | since a copy-pasted fixture supplies exactly the fake that guard is
    | satisfied by.
    */
    'authorizenet' => [
        'environment' => env('AUTHORIZE_NET_ENVIRONMENT', env('AUTHORIZENET_SANDBOX', false) ? 'sandbox' : 'production'),

        // Fifteen seconds, matching Stripe's and for the same reason: a person
        // is waiting on this call with a signup half finished, and a slow
        // success beats a fast abandonment.
        'timeout' => (int) env('AUTHORIZE_NET_TIMEOUT', 15),
    ],

];
