<?php

declare(strict_types=1);

use App\Enums\OauthProvider;

/*
|--------------------------------------------------------------------------
| The vendor surface of the token vault
|--------------------------------------------------------------------------
|
| Endpoints, scopes and grant shapes for every provider the vault refreshes.
| Configuration rather than code, for the same reason the model router is
| configuration: these move, and a moved endpoint should be an env change and a
| config edit, never a deploy that touches a service class.
|
| No secret appears here. Client ids and secrets live in config/services.php,
| under Socialite's own keys, and are read from the environment.
|
| Every figure below was read from the vendor's own live documentation on
| 2026-07-31; the citation and the documentation's own date sit beside each one.
| Treat them as stale on the next slice and re-read — that is the standing rule
| for this file.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Refresh window
    |--------------------------------------------------------------------------
    |
    | How long before expiry a token is considered due for refresh, in seconds.
    |
    | Five minutes rather than zero because a token that expires mid-flight
    | fails the call it was fetched for, and rather than an hour because every
    | early refresh is a request against a provider quota. Google access tokens
    | last ~3600s and Microsoft's report expires_in 3599, so this spends roughly
    | 8% of a token's life on safety margin.
    |
    */

    'refresh_window' => (int) env('OAUTH_REFRESH_WINDOW', 300),

    /*
    |--------------------------------------------------------------------------
    | Request timeout
    |--------------------------------------------------------------------------
    |
    | Seconds. Deliberately short, and deliberately the only knob: there is no
    | synchronous retry anywhere in this subsystem.
    |
    | Retries belong to the queue. A token endpoint that is slow or 5xx-ing gets
    | one attempt, a classified TokenRefreshFailed, and a job that comes back
    | later with jittered backoff — never an in-process sleep loop, which would
    | hold a web worker open for the duration of somebody else's outage.
    |
    */

    'timeout' => (int) env('OAUTH_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Keyed by App\Enums\OauthProvider values. A provider absent from this list
    | has no refresh path and the vault will say so rather than guess.
    |
    */

    'providers' => [

        /*
         * Google.
         *
         * https://developers.google.com/identity/protocols/oauth2/web-server
         * read 2026-07-31.
         *
         *   token endpoint   https://oauth2.googleapis.com/token
         *   refresh grant    client_id, client_secret, grant_type=refresh_token,
         *                    refresh_token
         *   response         access_token, expires_in, token_type, scope
         *                    — no new refresh_token; the existing one persists
         *   revoked/expired  error=invalid_grant
         *   revocation       https://oauth2.googleapis.com/revoke, param `token`
         *
         * NOTE: Socialite's own GoogleProvider posts to the legacy
         * https://www.googleapis.com/oauth2/v4/token. The vault uses the
         * currently documented endpoint above instead — see TokenService.
         *
         * business.manage is the single scope for every Google Business Profile
         * API (https://developers.google.com/my-business/content/basic-setup,
         * last updated 2025-08-28). Access is an application with a lead time and
         * new projects start at 0 QPM, so nothing may assume this scope works.
         */
        OauthProvider::Google->value => [
            'token_endpoint' => 'https://oauth2.googleapis.com/token',
            'revoke_endpoint' => 'https://oauth2.googleapis.com/revoke',

            // My Business Account Management API. Its own hostname, not
            // googleapis.com/mybusiness — GET /v1/accounts, scope
            // business.manage
            // (https://developers.google.com/my-business/reference/accountmanagement/rest/v1/accounts/list,
            // last updated 2024-10-16). pageSize default and maximum is 20.
            'account_management_endpoint' => 'https://mybusinessaccountmanagement.googleapis.com/v1',

            'scopes' => [
                'https://www.googleapis.com/auth/business.manage',
            ],
            // access_type=offline is what causes a refresh_token to be issued at
            // all; prompt=consent forces re-consent so reconnecting a broken
            // connection actually returns a new one rather than silently not.
            'authorize_parameters' => [
                'access_type' => 'offline',
                'prompt' => 'consent',
            ],

            /*
             * SIGNING IN IS NOT CONNECTING, and these are deliberately not the
             * scopes above. Sign-in needs to know who someone is; it does not
             * need permission to manage their business listing.
             *
             * Asking for business.manage at the sign-up screen would demand the
             * most consequential grant Google issues before the person has seen
             * the product — and it would fail anyway, because that scope needs
             * an approved access application behind it. The connect flow asks
             * separately, when there is a reason to.
             *
             * No access_type=offline either: a refresh token is for acting on
             * someone's behalf later, and sign-in never does.
             */
            'login_scopes' => ['openid', 'profile', 'email'],
            'login_parameters' => [],
        ],

        /*
         * Google Search Console — CONNECT ONLY, READ ONLY.
         *
         * Grant shape is Google's, identical to the entry above; only the scope
         * differs, which is why this is a separate provider rather than a second
         * scope on `google`. A tenant may sign in with Google, connect their
         * Business Profile, and connect Search Console, and those are three
         * different consents with three different revocation stories — one row
         * per grant is what lets one be revoked without taking the others.
         *
         * ⚠️ THE READ-ONLY SCOPE, NEVER THE READ-WRITE ONE (decision 1083). Both
         * are in the live discovery document at
         * https://searchconsole.googleapis.com/$discovery/rest?version=v1,
         * revision 20260804, read 2026-08-05:
         *
         *   .../auth/webmasters            "View and manage Search Console data
         *                                  for your verified sites" — this also
         *                                  permits SUBMITTING SITEMAPS and ADDING
         *                                  OR DELETING PROPERTIES. Nothing this
         *                                  product does needs any of it.
         *   .../auth/webmasters.readonly   "View Search Console data for your
         *                                  verified sites"
         *
         * The narrower grant is not merely tidy: `28` §5.3's whole principle is
         * "report what actually happened, from data we own. Never simulate a
         * search, never scrape Google" — a write scope on a reporting feature is
         * a capability with no caller and a blast radius, which is the shape
         * decision 272's family keeps producing.
         *
         * ⚠️ ITS OWN REDIRECT URI, and it must be registered in the Google Cloud
         * console alongside the sign-in one or the callback fails with
         * redirect_uri_mismatch. SearchConsoleConnectController overrides
         * Socialite's configured redirect per call; the OAuth client is shared
         * with sign-in, the callback route is not.
         *
         * ⚠️ NO `login_scopes` / `login_parameters` KEYS ON PURPOSE. Their absence
         * is what makes it structurally impossible for OauthLoginController to
         * drive this provider even if `LOGIN_PROVIDERS` were widened by mistake
         * — decision 1082 forbids the widening; this makes the mistake fail
         * rather than silently sign somebody in with a reporting grant.
         *
         * Quota (https://developers.google.com/webmaster-tools/limits, read
         * 2026-08-05): Search Analytics 1,200 QPM per site and per user, 40,000
         * QPM per project; everything else 200 QPM per user. See
         * App\Services\Gsc\GoogleSearchConsoleClient for why no rate governor
         * ships with this.
         */
        OauthProvider::Gsc->value => [
            'token_endpoint' => 'https://oauth2.googleapis.com/token',
            'revoke_endpoint' => 'https://oauth2.googleapis.com/revoke',

            'scopes' => [
                'https://www.googleapis.com/auth/webmasters.readonly',
            ],

            // Same reasoning as Google above: access_type=offline is what causes
            // a refresh token to be issued at all, and prompt=consent forces
            // re-consent so reconnecting a broken connection actually returns a
            // new one rather than silently not. A Search Console sync runs daily
            // and unattended, so a connection with no refresh token is one that
            // dies in an hour and never reports again.
            'authorize_parameters' => [
                'access_type' => 'offline',
                'prompt' => 'consent',
            ],
        ],

        /*
         * Microsoft (Entra ID v2.0).
         *
         * https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow
         * updated 2026-06-15.
         *
         *   token endpoint   https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token
         *   refresh grant    client_id, client_secret, grant_type=refresh_token,
         *                    refresh_token, scope
         *   response         access_token, expires_in, scope, AND a NEW
         *                    refresh_token — "You're expected to discard the old
         *                    refresh token." The vault persists the rotation.
         *   errors           {error, error_description, error_codes[], timestamp,
         *                    trace_id, correlation_id}
         *
         * offline_access is required or no refresh_token is issued at all.
         * Mail.Send and Calendars.ReadWrite carry the graph.microsoft.com prefix
         * (https://learn.microsoft.com/en-us/graph/permissions-reference,
         * updated 2026-07-28). Mail.Send is the least-privileged permission for
         * POST /me/sendMail (https://learn.microsoft.com/en-us/graph/api/user-sendmail,
         * updated 2026-06-19).
         *
         * `tenant` is 'common' for multi-tenant consumer + work accounts. A
         * single-tenant deployment sets its directory id instead.
         */
        OauthProvider::Microsoft->value => [
            'token_endpoint' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
            'authorize_endpoint' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize',
            'graph_endpoint' => 'https://graph.microsoft.com/v1.0',
            'scopes' => [
                'openid',
                'profile',
                'email',
                'offline_access',
                'https://graph.microsoft.com/Mail.Send',
                'https://graph.microsoft.com/Calendars.ReadWrite',
            ],
            'authorize_parameters' => [],

            // Sign-in only — see the note under Google's login_scopes. No
            // offline_access: nothing acts on the person's behalf here, so there
            // is no reason to hold a refresh token from a login.
            'login_scopes' => ['openid', 'profile', 'email', 'User.Read'],
            'login_parameters' => [],
        ],

        /*
         * Meta (Facebook).
         *
         * https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived
         * read 2026-07-31.
         *
         * Meta issues NO refresh token. There is no refresh_token grant. The
         * only mechanism is exchanging a still-valid token for a longer-lived
         * one:
         *
         *   GET /oauth/access_token
         *       ?grant_type=fb_exchange_token
         *       &client_id=&client_secret=&fb_exchange_token=<current token>
         *
         * A long-lived user token lasts ~60 days, and — this is the part that
         * shapes the design — "You can not use an expired token to request a
         * long-lived token. If the token has expired, your app must send the
         * user through the login flow again."
         *
         * So Meta cannot refresh transparently the way FOUND-03 assumes. The
         * vault extends while the token is still valid and raises Reconnect the
         * moment it is not. See MetaTokenRefresher.
         *
         * Graph v26.0 released 2026-07-29
         * (https://developers.facebook.com/docs/graph-api/changelog). Pinned, not
         * defaulted: omitting the version makes Meta serve the OLDEST available
         * version. Socialite's own FacebookProvider still pins v23.0.
         */
        OauthProvider::Facebook->value => [
            'graph_version' => env('META_GRAPH_VERSION', 'v26.0'),
            'graph_endpoint' => 'https://graph.facebook.com',
            'scopes' => [
                'public_profile',
                'pages_show_list',
                'pages_read_engagement',
                'pages_manage_posts',
            ],
            'authorize_parameters' => [],
        ],

    ],

];
