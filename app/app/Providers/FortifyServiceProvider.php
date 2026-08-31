<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Enums\UserRole;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasswordResetFailedResponse;
use App\Http\Responses\PasswordResetLinkRequestedResponse;
use App\Http\Responses\RegisterResponse;
use App\Services\Oauth\MicrosoftSocialiteProvider;
use App\Support\HashedIp;
use App\Support\MagicLinkRateLimits;
use App\Support\PasswordResetRateLimits;
use App\Support\RegistrationRateLimits;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Horizon\Horizon;
use Laravel\Socialite\Socialite;

/**
 * Authentication wiring (FOUND-04).
 *
 * Fortify covers password, two-factor and passkeys. The two things it does not
 * cover are registered here too, so there is one file to read when asking "how
 * does someone get in?": the Microsoft Socialite driver, which does not ship
 * with Socialite, and the role gates.
 */
class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Where a newly registered person lands: Checkout, so that decision 98's
        // "credit card required" is actually asked for. Bound here rather than
        // by moving `fortify.home`, which is also where *login* goes — see the
        // response class. In register() because a contract binding must exist
        // before Fortify's controller resolves it.
        $this->app->singleton(
            RegisterResponseContract::class,
            RegisterResponse::class,
        );

        // Where a person lands after *signing in*, which `fortify.home` cannot
        // answer because it is one string and there are three account shapes —
        // decision 5490. Staff have no tenant and the wizard refuses them; a
        // finished owner wants their dashboard, not the wizard's terminus. Same
        // seam and same reasoning as the binding above, which already noted that
        // `home` "is also where login goes".
        $this->app->singleton(
            LoginResponseContract::class,
            LoginResponse::class,
        );

        // ⛔ AND THE SECOND-FACTOR CONTRACT, WHICH IS THE ONE STAFF ACTUALLY
        // TRAVEL THROUGH. `RequiresTwoFactor` obliges staff to carry a second
        // factor, so their sign-in ends in TwoFactorLoginResponse — whose
        // default also reads `fortify.home`. Binding only the line above would
        // have left every account this change exists for landing on /setup.
        $this->app->singleton(
            TwoFactorLoginResponseContract::class,
            LoginResponse::class,
        );

        // ⛔ ONE CLASS BOUND TO BOTH ARMS OF `POST /forgot-password`, WHICH IS
        // WHAT CLOSES 9505's ORACLE. Fortify branches on the broker's status
        // and rendered `passwords.user` — "We can't find a user with that email
        // address." — to anybody who asked. Binding a second, differently-named
        // class to each contract would be two strings that can drift; the
        // property being kept is that the arms are INDISTINGUISHABLE, not that
        // they read alike today. `bind`, not `singleton`, because Fortify
        // resolves both with `app($contract, ['status' => $status])` and a
        // shared instance would be built once and handed a stale parameter.
        $this->app->bind(
            SuccessfulPasswordResetLinkRequestResponseContract::class,
            PasswordResetLinkRequestedResponse::class,
        );

        $this->app->bind(
            FailedPasswordResetLinkRequestResponseContract::class,
            PasswordResetLinkRequestedResponse::class,
        );

        // ⛔ AND THE SECOND ORACLE, WHICH NO DECISION HAD NAMED: `POST
        // /reset-password` answers "This password reset token is invalid." for
        // an address that has an account and "We can't find a user with that
        // email address." for one that does not — with a token made up on the
        // spot, because `PasswordBroker::validateReset()` looks the user up
        // first. One message for every failure. The SUCCESS arm stays Fortify's
        // and is deliberately untouched.
        $this->app->bind(
            FailedPasswordResetResponseContract::class,
            PasswordResetFailedResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        $this->registerMicrosoftDriver();
        $this->registerRoleGates();

        // ⛔ THE KEY IS `HashedIp` AND IT WAS `$request->ip()` UNTIL 2026-08-25
        // (9722). `RegistrationRateLimits` flagged the raw address here in slice
        // F and said "the next person to read this file is the person who should
        // fix it"; wave 29's lane C read it and left it (9629c) on the honest
        // grounds that re-keying the busiest auth route is a behaviour change
        // that needs its own tests. It has them now —
        // `tests/Feature/Auth/MagicLinkRateLimitTest.php`'s login arms — and
        // this is the last limiter in the application still keying on a raw
        // address, which is 9620's rule: `ThrottleRequests` stores
        // `md5($limiterName.$limit->key)`, unsalted, and the IPv4 space is 2^32.
        //
        // ⚠️ THE BUCKET GRANULARITY IS DELIBERATELY UNCHANGED, AND THAT IS THE
        // WHOLE OF WHY THIS IS SAFE. The submitted address is still in the key,
        // so this is still per-credential: one person's failures cannot lock out
        // everybody behind their router, which is right for a credential check
        // and is the property 9629(c) called worth keeping. Only the second half
        // of the key changed shape.
        //
        // ⛔ AND THE SPRAYING GAP IS NOT CLOSED BY THIS OR BY ANY FIGURE HERE
        // (9723). An attacker rotating the email field gets a fresh bucket per
        // address, so one guess against N accounts is bounded by nothing from
        // one source — and an outer per-address ceiling beside this one, the
        // repair `RegistrationRateLimits` and 9623 would suggest, CANNOT
        // separate a sprayer from a large tenant's office at nine in the
        // morning, because spraying is defined by being low volume per address.
        // What bounds it is a per-account lockout, a captcha or stuffing
        // detection: a product decision and a support surface, and the owner's.
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower((string) $request->input(Fortify::username()))
                .'|'.(HashedIp::of($request) ?? 'login:unknown-origin')
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // ⚠️ THE SAME RE-KEY, AND IT IS HERE BECAUSE THE SENTENCE ABOVE WOULD
        // OTHERWISE BE FALSE (9722). `passkeys` carried `$request->ip()` too;
        // leaving one raw-address limiter behind while claiming the last one was
        // gone is exactly the shape this slice is about. Its exposure is smaller
        // — six of the seven passkey routes are authenticated — and the argument
        // for the key is identical, so there was nothing to weigh.
        //
        // ⚠️ GRANULARITY UNCHANGED AGAIN: the credential id, or the session id
        // where there is none, is still the first half of the key.
        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId())
                .'|'.(HashedIp::of($request) ?? 'passkeys:unknown-origin')
            );
        });

        // ⛔ THE THREE LIMITERS FORTIFY HAS NO HOOK FOR. Its route file reads
        // `fortify.limiters.login`, `.two-factor`, `.passkeys` and
        // `.verification` and nothing else, so `POST /forgot-password`,
        // `POST /reset-password` and `POST /register` shipped on
        // `['web', 'guest:web']` and no throttle at all — measured at the
        // route, not read. `routes/web.php` is what puts these on those three
        // doors; this is only where they are defined.
        //
        // ⚠️ HERE RATHER THAN IN `AppServiceProvider` BESIDE THE OTHER NINE
        // `*RateLimits::register()` CALLS, because the three above it in this
        // method are the rest of the auth surface and reading half of one
        // control in each of two providers is how the next person finds only
        // half of it.
        PasswordResetRateLimits::register();
        RegistrationRateLimits::register();

        // ⛔ AND THE TWO FORTIFY HAS NO HOOK FOR BECAUSE THEY ARE NOT FORTIFY'S
        // (9720). The magic-link legs are ours, in `routes/web.php`, and they
        // carried `throttle:5,1` and `throttle:10,1` — the only two inline
        // throttles this application declares, the third being Livewire's own
        // upload route. An inline throttle has no key
        // parameter at all: `ThrottleRequests` falls through to
        // `resolveRequestSignature()`, which is `sha1($domain.'|'.$ip)`, so the
        // request leg was 300 an hour on the raw address while the sibling door
        // above it was five an hour on a `HashedIp`.
        //
        // ⚠️ HERE FOR THE REASON THE PARAGRAPH ABOVE GIVES, WHICH IS THE ONLY
        // ONE THAT MATTERS: this is the auth surface, and reading half of one
        // control in each of two providers is how the next person finds only
        // half of it.
        MagicLinkRateLimits::register();
    }

    /**
     * Teach Socialite about Microsoft.
     *
     * Socialite ships eight drivers and Microsoft is not one of them (Facebook,
     * X, LinkedIn, Google, GitHub, GitLab, Bitbucket, Slack — verified against
     * the 13.x documentation on 2026-07-31). The usual answer is
     * `socialiteproviders/microsoft`, which is not an approved dependency, so
     * the driver is first-party and extends Socialite's AbstractProvider — which
     * means Socialite's own `state` generation, session storage and constant-time
     * comparison are reused rather than reimplemented. See
     * MicrosoftSocialiteProvider.
     */
    private function registerMicrosoftDriver(): void
    {
        Socialite::extend('microsoft', function ($app): MicrosoftSocialiteProvider {
            /** @var array{client_id: string|null, client_secret: string|null, redirect: string|null} $config */
            $config = $app['config']['services.microsoft'];

            /** @var MicrosoftSocialiteProvider $provider */
            $provider = Socialite::buildProvider(MicrosoftSocialiteProvider::class, $config);

            return $provider;
        });
    }

    /**
     * The abilities that are about a role rather than about a row.
     *
     * Row-level authorization lives in policies (AutopilotSettingsPolicy,
     * OauthConnectionPolicy). These two have no model to hang off — "may this
     * person reach the control plane at all?" is not a question about any
     * particular record — so they are gates.
     */
    private function registerRoleGates(): void
    {
        // The platform control plane. Not agencies: an agency manages tenants,
        // it is not the platform, and the difference is one customer's data
        // versus everyone's.
        Gate::define(
            'access-platform-admin',
            fn ($user): bool => $user->role instanceof UserRole && $user->role->canAdministerPlatform(),
        );

        // Horizon's dashboard is the same boundary by another door: it shows
        // every tenant's job payloads and tags.
        Gate::define(
            'viewHorizon',
            fn ($user): bool => $user->role instanceof UserRole && $user->role->canAdministerPlatform(),
        );

        // ⛔ DEFINING THE GATE IS NOT ENOUGH AND FOR EIGHT MONTHS IT WAS ALL WE
        // DID (decisions 6327, 6328). Horizon does not look this gate up. It asks
        // `Horizon::check()`, which falls back to `app()->environment('local')`
        // when nothing has called `Horizon::auth()` — and nothing here ever had.
        // So the gate above was dead code: `/horizon` was open to an
        // UNAUTHENTICATED request on every developer machine and in CI, and
        // refused `super_admin` in production, where the fallback is false.
        //
        // ⚠️ THE TEST THAT SHOULD HAVE CAUGHT IT ASSERTED THE GATE'S ANSWER
        // (`RoleAuthorizationTest`, "the horizon dashboard is the same boundary
        // by another door") AND PASSED PERFECTLY, because a gate nobody consults
        // still answers correctly when you ask it yourself. That is 272's shape
        // — a control with no reader — on the widest read surface this
        // application serves.
        //
        // ⛔ NO `app()->environment('local')` ESCAPE HATCH. Laravel's own
        // published HorizonServiceProvider writes
        // `app()->environment('local') || Gate::check(...)`; that disjunction IS
        // the hole this closes, and copying it back would reopen it. Local dev
        // connects as a non-owner role here precisely so it behaves like
        // production — the same reasoning applies to this door.
        Horizon::auth(
            static fn (Request $request): bool => Gate::forUser($request->user())->allows('viewHorizon'),
        );
    }
}
