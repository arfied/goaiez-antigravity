<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\OauthProvider;
use App\Enums\TermsAcceptanceMethod;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Legal\SignupTerms;
use App\Services\Legal\SignupTermsUnavailable;
use App\Services\Legal\TermsAcceptances;
use App\Services\TenantProvisioner;
use App\Support\Auth\SecondFactor;
use App\Support\Auth\StaffSession;
use App\Support\HashedIp;
use App\Support\RegistrationRateLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Google and Microsoft single sign-on.
 *
 * SIGN-IN ONLY. This is the identity leg — it establishes who someone is and
 * nothing more. It deliberately does NOT store the returned tokens in the vault,
 * even though Socialite hands them over and it would be one line.
 *
 * The two are different operations with different consent. Signing in with
 * Google asks for `openid profile email`; connecting a Google Business Profile
 * asks for `business.manage`, which is an access application with a lead time
 * and a 60-day-verified profile behind it. Conflating them means either asking
 * every new signup for permission to manage their business listing before they
 * have seen the product, or storing a connection whose scopes cannot do the job
 * the connection implies. The connect flow is its own screen, and it stores
 * through TokenService.
 *
 * MICROSOFT IS A FIRST-PARTY DRIVER. Socialite ships no Microsoft provider, and
 * `socialiteproviders/microsoft` is not an approved dependency —
 * MicrosoftSocialiteProvider extends AbstractProvider so that Socialite's own
 * state generation, session storage and constant-time comparison are reused
 * rather than reimplemented.
 */
final class OauthLoginController extends Controller
{
    /**
     * Providers that may be used to *sign in*.
     *
     * An allowlist, not a validation rule, and not the whole OauthProvider enum.
     * Without it, `/auth/{provider}/redirect` accepts anything Socialite can be
     * asked for — including drivers with no configuration, which fail in
     * confusing ways — and it would grow silently as connect-only providers are
     * added to the enum. Signing in with Stripe is not a feature.
     *
     * @var list<value-of<OauthProvider>>
     */
    private const LOGIN_PROVIDERS = ['google', 'microsoft'];

    public function __construct(
        private readonly TenantProvisioner $tenants,
        private readonly SignupTerms $terms,
        private readonly TermsAcceptances $acceptances,
    ) {}

    /**
     * Send someone to the provider.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $driver = self::driverFor($provider);

        /** @var AbstractProvider $socialite */
        $socialite = Socialite::driver($driver->value);

        /** @var list<string> $scopes */
        $scopes = config("oauth.providers.{$driver->value}.login_scopes", ['openid', 'profile', 'email']);

        /** @var array<string, string> $parameters */
        $parameters = config("oauth.providers.{$driver->value}.login_parameters", []);

        return $socialite->setScopes($scopes)->with($parameters)->redirect();
    }

    /**
     * Come back from the provider and sign in.
     *
     * ⚠️ **RETURNS `Response` RATHER THAN `RedirectResponse` BECAUSE THE LAST
     * LINE IS NOW SOMEBODY ELSE'S ANSWER** — see the sign-in destination note
     * at the bottom of this method. Every other arm here still returns a
     * redirect; the type is widened to whatever {@see LoginResponseContract}
     * hands back, which for a browser is a redirect and for a JSON client is a
     * 200 with no body.
     */
    public function callback(Request $request, string $provider): Response
    {
        $driver = self::driverFor($provider);

        try {
            /** @var AbstractProvider $socialite */
            $socialite = Socialite::driver($driver->value);

            $identity = $socialite->user();
        } catch (InvalidStateException) {
            // The state did not match: a stale tab, a session that expired
            // mid-flow, or a forged callback. Indistinguishable from here, and
            // all three end the same way.
            return redirect()->route('login')->withErrors([
                'email' => 'That sign-in did not complete. Please try again.',
            ]);
        }

        $email = $identity->getEmail();

        if (! is_string($email) || $email === '') {
            // Both providers can return a profile with no address — Microsoft
            // for an account with no licensed mailbox, Google if the email scope
            // was declined. Without one there is nothing to match on and nothing
            // to contact, so this fails rather than inventing an identity.
            return redirect()->route('login')->withErrors([
                'email' => 'That account did not share an email address, so we could not sign you in.',
            ]);
        }

        try {
            $user = $this->findOrCreate($request, $email, $identity);
        } catch (ValidationException $e) {
            // ⚠️ CAUGHT HERE AND TURNED INTO THIS CONTROLLER'S OWN IDIOM, rather
            // than left to the handler. A `ValidationException` out of a **GET**
            // callback redirects *back*, and "back" from an OAuth callback is
            // the provider's own consent screen — so the refusal would bounce
            // the person to Google with no message. The one thrower is the
            // registration velocity limit in findOrCreate(); every other line in
            // this method reports failure the same way.
            //
            // ⚠️ **BY TYPE, SO IT IS ONE VALIDATING COLLABORATOR AWAY FROM BEING
            // WRONG.** `RegistrationRateLimits` is the only thrower on this path
            // today — grepped, not assumed — but `provision()` is a long call
            // chain, and the first thing inside it to validate anything would be
            // reported to the person as "try again later". If that happens,
            // narrow this to a rethrown sentinel rather than widening the copy.
            //
            // ⚠️ **AND THE TWO DOORS REPORT THE SAME REFUSAL DIFFERENTLY**: the
            // limiter sets 429, which `POST /register` keeps and this 302
            // discards. Harmless for a person, and worth knowing before anybody
            // builds a client that keys on the status.
            return redirect()->route('login')->withErrors([
                'email' => $e->errors()['email'][0] ?? $e->getMessage(),
            ]);
        } catch (SignupTermsUnavailable) {
            // ⛔ **NO ACCOUNT IS OPENED ON TERMS NOBODY HAS PUBLISHED** (T176
            // P22). Caught by its own type rather than folded into the
            // `ValidationException` arm above, on that arm's own instruction:
            // it is scoped to the velocity limiter, and widening it would
            // report an unpublished Terms of Service to the visitor as "try
            // again later".
            //
            // ⚠️ The wording says nothing about paperwork. The person cannot
            // act on it, and this door is only ever refused for as long as it
            // takes an admin to publish counsel's text.
            return redirect()->route('login')->withErrors([
                'email' => 'We cannot open new accounts just now. Please try again shortly.',
            ]);
        }

        $request->session()->regenerate();

        // Same reasoning as the magic link: an SSO user has no password, so
        // without this their first passkey is unreachable. See
        // MagicLinkController::markInteractivelyAuthenticated() — including why
        // it has to come before the challenge rather than after it.
        MagicLinkController::markInteractivelyAuthenticated($request);

        // ⚠️ BEFORE Auth::login(). SSO is the second of the two routes outside
        // Fortify's login pipeline, so it walked past a confirmed second factor
        // for the same reason the magic link did (decision 661).
        //
        // The provider may well have enforced its own 2FA — and we cannot know
        // that it did, cannot know which factors, and cannot record it. `28`
        // §9.1 makes the factor ours to demand, so it is demanded here rather
        // than assumed of Google or Microsoft.
        if ($challenge = SecondFactor::challenge($request, $user)) {
            return $challenge;
        }

        // ⚠️ Not `remember: true`. See MagicLinkController for the whole reason,
        // and StaffSession for the policy — an internal account is never handed
        // a five-year bearer credential that outlives `28` §9.1's twelve-hour
        // ceiling. This route is the one that made the guard inside
        // `StaffSession::applies()` mandatory rather than defensive: a
        // first-time SSO user was created moments ago on this same request, so
        // `users.role` is still absent in memory (decision 753).
        Auth::login($user, remember: StaffSession::remember($user));

        // ⛔ **THE SIGN-IN DESTINATION IS ONE ANSWER FOR ALL FOUR DOORS, AND
        // THIS DOOR ANSWERED IT ITSELF UNTIL 9156.** It read
        // `redirect()->intended(config('fortify.home', '/'))` — the same line
        // MagicLinkController ended on — so two of the four ways into this
        // application never reached {@see LoginResponse}. `fortify.home` is one
        // string and there are three account shapes (5490), so an owner who
        // finished onboarding months ago was sent back into the wizard whenever
        // they signed in with Google or Microsoft, and the staff case that
        // response class was *built* for was answered here by a constant.
        //
        // ⚠️ **`route('login')` ABOVE IS THE RULE THIS BREAKS AND THE RULE
        // 661 STATES**: a control on the sign-in path must not depend on which
        // of the four doors was used. `SecondFactor::challenge()` above is the
        // same rule, already obeyed, one concern over — and it is why the staff
        // half of this defect was *invisible*: a staff account holding a
        // confirmed factor is challenged before it ever gets here, completes
        // inside Fortify, and lands on `TwoFactorLoginResponse` — which is this
        // same class. Staff were routed correctly by accident of `28` §9.1,
        // not by anything this line did.
        //
        // The contract rather than the concrete class, so that rebinding it
        // moves all four doors at once — which is the whole property.
        // `intended()` lives inside it, so a person bounced here from a page
        // they asked for still arrives there.
        return app(LoginResponseContract::class)->toResponse($request);
    }

    /**
     * Match on the verified email, creating the account — and its tenant — on
     * first sign-in.
     *
     * Matching on email is safe *because* it is the provider's, and both
     * providers verify it before releasing it. Matching on the provider's
     * subject id instead would be stricter still, but needs a column per
     * provider on `users`, which DATA-MODEL §5.2 does not have — and adding one
     * belongs with the connect flow that actually needs to distinguish accounts,
     * not here.
     *
     * The role is left at the column default rather than assigned. A first user
     * becomes an Owner; anyone invited later is created by the invite flow with
     * the role that flow decided, and SSO must not be able to promote them.
     *
     * PROVISIONING RUNS IN THE SAME TRANSACTION AS THE INSERT, on a brand new
     * user only — the same shape and the same reason as `CreateNewUser`
     * (decision 271): a user without a business cannot be repaired, because
     * `ResolveTenant` resolves the tenant from a `Business` the user owns, and
     * every repair path is itself tenant-scoped. Before `fortify.home` pointed at
     * `/setup`, a first-time SSO user with no business landed on the public
     * marketing page and the gap was invisible; it is reachable now. An
     * *existing* user matched here (a password or magic-link account signing in
     * with SSO for the first time, or a teammate invited onto someone else's
     * tenant) already has — or will already have — a business through whichever
     * path created them, so provisioning must never run on that branch.
     */
    private function findOrCreate(Request $request, string $email, SocialiteUser $identity): User
    {
        $email = Str::lower($email);

        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            // Fill in what we did not have, never overwrite what we did — the
            // person may have set a name here that they would not recognise
            // coming back from a directory.
            $user->forceFill([
                'name' => $user->name !== '' ? $user->name : (string) $identity->getName(),
                'avatar_url' => $user->avatar_url ?? $identity->getAvatar(),
                // The provider verified the address to release it, so an account
                // that arrived unverified is verified now.
                'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
            ])->save();

            return $user;
        }

        // ⛔ THE SECOND PROVISIONING DOOR, AND IT WAS UNMETERED WHILE
        // `RegistrationRateLimits` CLAIMED THERE WAS ONLY ONE (3233, correcting
        // 3225).
        //
        // Every word that docblock writes about `POST /register` is true of the
        // line below: it creates a user, a business, a location, a feedback
        // page, a widget key, a subscription row, and it claims an Infobip
        // number out of a finite pool. A Google account is cheap and this round
        // trip is scriptable, so guarding one door and not the other left the
        // burst control worth nothing — an attacker just uses the other door.
        //
        // ⚠️ **ON THE NEW-USER BRANCH ONLY, AND THAT PLACEMENT IS THE WHOLE
        // CARE.** Everything above this line signs in somebody who already has
        // an account, and throttling *that* as a registration would lock a real
        // customer out of their own product for an hour because three other
        // people signed in from their office. This limiter counts accounts
        // created, never sign-ins attempted; Fortify's `login` limiter is what
        // covers the other half.
        //
        // ⚠️ AND OUTSIDE THE TRANSACTION, on `CreateNewUser`'s reasoning: the
        // limiter writes to the cache, a rollback would not take that write
        // back, and a refused registration that still consumed its slot is a
        // limiter punishing the wrong attempt.
        RegistrationRateLimits::enforce($request);

        // ⛔ **THE SECOND DOOR NEEDS THE SAME TERMS AS THE FIRST** (T176 P22).
        // `POST /register` refuses when the Terms, SMS terms or Privacy Policy
        // are unpublished; a Google account that walked past that check would
        // open a tenant with no agreement behind it — the same shape as the
        // unmetered door 3233 found here, one control over.
        //
        // Asked before the transaction so the refusal costs nothing, and asked
        // again inside `TermsAcceptances::record()` because a check in a
        // controller is a check the next caller bypasses (398).
        $this->terms->requireCurrent();

        return DB::transaction(function () use ($request, $email, $identity): User {
            $user = new User;

            $user->forceFill([
                'name' => (string) ($identity->getName() ?? $email),
                'email' => $email,
                'avatar_url' => $identity->getAvatar(),
                // Never a random password. A NOT NULL password column is what
                // forces that pattern, and the migration removed the constraint
                // precisely so no credential exists that nobody holds.
                'password' => null,
                'email_verified_at' => Carbon::now(),
            ])->save();

            // ⛔ **THE MODEL IS RE-READ BECAUSE `users.role` IS THE MIGRATION'S
            // COLUMN DEFAULT AND NOT SOMETHING THIS INSERT SET** (753, and 9156
            // is its second victim). A just-`create()`d `User` carries **no role
            // in memory**, and this is the one request in the application where
            // that is true of a person who is about to be signed in.
            // `StaffSession::applies()` carries an `instanceof` guard for
            // exactly this and its docblock ends *"anything new reading a role
            // on the login path needs this guard"* — which is a note saying the
            // footgun is still loaded. Routing this door through
            // `LoginResponse` made a second reader of that role, and the first
            // run of the new test was a 500 out of
            // `$user->role->isTenantRole()`.
            //
            // ⚠️ **A REFRESH RATHER THAN `'role' => UserRole::Owner`.** The
            // docblock above says in terms that the role is left at the column
            // default and that SSO must never assign one; spelling the default
            // here would be a second source of truth for it, drifting silently
            // the day the migration's default changes. This reads back what the
            // database actually decided, inside the transaction that decided it.
            $user->refresh();

            // No audit token: the SSO redirect carries nothing equivalent to
            // `/register`'s `audit` field, so this tenant provisions with an
            // empty pre-fill — the same outcome as an unknown or absent token
            // on the password path, and never an error.
            $this->tenants->provision($user);

            // ⚠️ **ACCEPTANCE BY NOTICE, RECORDED AS SUCH.** The login page
            // renders `TermsAcceptanceMethod::SsoContinue->notice()` beside the
            // provider buttons, so pressing one is the act — there is no box,
            // and `TermsAcceptanceMethod` is what keeps this from being stored
            // as though there were.
            //
            // ⚠️ **THE PROOF URL IS THIS CALLBACK, NOT THE PAGE THAT RENDERED
            // THE NOTICE**, which is one redirect earlier and not knowable from
            // here. The stored wording is the notice itself, so what the person
            // was shown is answerable; where they were shown it is answerable
            // only as "the page carrying the button". Said plainly rather than
            // papered over — this is the weaker of the two doors.
            $this->acceptances->record(
                TermsAcceptanceMethod::SsoContinue,
                [
                    'url' => $request->url(),
                    'ip_hash' => HashedIp::of($request),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
                ],
                'user:'.$user->getKey(),
            );

            return $user;
        });
    }

    /**
     * Resolve a URL segment to a provider, or 404.
     */
    private static function driverFor(string $provider): OauthProvider
    {
        if (! in_array($provider, self::LOGIN_PROVIDERS, true)) {
            throw new NotFoundHttpException;
        }

        return OauthProvider::from($provider);
    }
}
