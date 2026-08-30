<?php

use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Fortify Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Fortify will use while
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Fortify Password Broker
    |--------------------------------------------------------------------------
    |
    | Here you may specify which password broker Fortify can use when a user
    | is resetting their password. This configured value should match one
    | of your password brokers setup in your "auth" configuration file.
    |
    */

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Username / Email
    |--------------------------------------------------------------------------
    |
    | This value defines which model attribute should be considered as your
    | application's "username" field. Typically, this might be the email
    | address of the users but you are free to change this value here.
    |
    | Out of the box, Fortify expects forgot password and reset password
    | requests to have a field named 'email'. If the application uses
    | another name for the field you may define it below as needed.
    |
    */

    'username' => 'email',

    'email' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Lowercase Usernames
    |--------------------------------------------------------------------------
    |
    | This value defines whether usernames should be lowercased before saving
    | them in the database, as some database system string fields are case
    | sensitive. You may disable this for your application if necessary.
    |
    */

    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Home Path
    |--------------------------------------------------------------------------
    |
    | Here you may configure the path where users will get redirected during
    | authentication or password reset when the operations are successful
    | and the user is authenticated. You are free to change this value.
    |
    */

    /*
     * Where Fortify sends a person after they authenticate.
     *
     * The onboarding wizard (`29` §7.2). Before it existed this pointed at '/'
     * — the marketing page — because there was no authenticated owner-facing
     * route at all, and a registered owner landed back where they started.
     */
    'home' => '/setup',

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Prefix / Subdomain
    |--------------------------------------------------------------------------
    |
    | Here you may specify which prefix Fortify will assign to all the routes
    | that it registers with the application. If necessary, you may change
    | subdomain under which all of the Fortify routes will be available.
    |
    */

    'prefix' => '',

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Middleware
    |--------------------------------------------------------------------------
    |
    | Here you may specify which middleware Fortify will assign to the routes
    | that it registers with the application. If necessary, you may change
    | these middleware but typically this provided default is preferred.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | By default, Fortify will throttle logins to five requests per minute for
    | every email and IP address combination. However, if you would like to
    | specify a custom rate limiter to call then you may specify it here.
    |
    */

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
        'passkeys' => 'passkeys',
    ],

    /*
    |--------------------------------------------------------------------------
    | Register View Routes
    |--------------------------------------------------------------------------
    |
    | Here you may specify if the routes returning views should be disabled as
    | you may not need them when building your own application. This may be
    | especially true if you're writing a custom single-page application.
    |
    */

    /*
     * FALSE, deliberately, and it drops only the GET routes that return views —
     * every POST endpoint Fortify registers (login, logout, register, password
     * reset, two-factor, passkeys) is unaffected.
     *
     * Fortify's seven default screens are unstyled scaffolding that would be
     * rebuilt from the design system in `22` before anyone saw them, and having
     * them registered but view-less is what makes an unconfigured route 500 with
     * a debug page rather than 404. So the auth *backend* is this ticket and the
     * auth *screens* are the design slice's; the two views that must exist for
     * the framework itself to work — `login`, because Authenticate redirects
     * there, and `password.reset`, because the reset notification builds its URL
     * from that route name — are registered in routes/web.php.
     */
    'views' => false,

    /*
    |--------------------------------------------------------------------------
    | Passkeys
    |--------------------------------------------------------------------------
    |
    | These settings configure Fortify's passkey (WebAuthn) support. Passkeys
    | allow users to sign in without needing to remember credentials since
    | they use public-key cryptography - making them immune to breaches.
    |
    */

    'passkeys' => [
        'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
        'allowed_origins' => [config('app.url')],
        'timeout' => 60000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Some of the Fortify features are optional. You may disable the features
    | by removing them from this array. You're free to only remove some of
    | these features or you can even remove all of these if you need to.
    |
    */

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        // Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
            // 'window' => 0,
        ]),
        /*
         * confirmPassword stays TRUE, which is Fortify's own recommendation and
         * the safer setting — a hijacked session must not be able to register a
         * permanent credential.
         *
         * It creates one problem worth naming, because it is not obvious and it
         * bites exactly the people passkeys are for. Three of this application's
         * four login methods never establish a password, so a magic-link or SSO
         * user has nothing to confirm with, and confirming with a passkey needs
         * a passkey they do not have yet. Their first passkey would be
         * unreachable.
         *
         * The fix is in the login controllers rather than here: completing a
         * magic-link or SSO sign-in marks the session password-confirmed,
         * because the person has just finished an interactive authentication
         * that proves what a password would. See MagicLinkController and
         * OauthLoginController.
         */
        Features::passkeys([
            'confirmPassword' => true,
        ]),
    ],

];
