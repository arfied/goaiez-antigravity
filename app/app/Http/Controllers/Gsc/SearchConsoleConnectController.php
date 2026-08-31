<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gsc;

use App\Enums\OauthProvider;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\OauthConnection;
use App\Services\Oauth\TokenService;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as OauthTwoUser;
use Masmerise\Toaster\Toaster;

/**
 * Connecting a tenant's Google Search Console — and nothing else.
 *
 * ⚠️ **WHY THIS IS NOT A THIRD ENTRY IN `OauthLoginController::LOGIN_PROVIDERS`.**
 * Decision 1082: widening that allowlist would make `/auth/gsc/redirect` a
 * working **sign-in** route, so somebody could authenticate to this application
 * with a Search Console reporting grant. That is an authentication decision made
 * as a side effect of a reporting feature, and that controller's own docblock
 * anticipates it in as many words — *"the set of providers you may sign in with
 * is smaller than the set the platform connects to"*. `OauthProvider::Gsc` had
 * existed as an enum case with **no writer anywhere** since Stage 0 (272's shape
 * applied to an enum case); this controller is what gives it one, on the connect
 * side only.
 *
 * CONNECTING IS NOT SIGNING IN, and the two differ in every way that matters
 * here. Sign-in establishes who somebody is and deliberately discards the tokens.
 * This establishes what we may read on their behalf, so it stores them — through
 * `TokenService`, which is the only encrypted credential store in this
 * application and gets no second copy.
 *
 * ⚠️ **IT OVERRIDES SOCIALITE'S REDIRECT URI PER CALL.** The OAuth client is
 * shared with sign-in (one Cloud project, one client id), so the callback URL is
 * the only thing separating the two flows. **Both URIs must be registered as
 * authorized redirect URIs in the Google Cloud console** or this fails with
 * `redirect_uri_mismatch` — an operational fact with no code representation,
 * stated here because the failure names neither this file nor that setting.
 *
 * ## Two refusals that look like over-engineering and are not
 *
 * **A declined scope.** Google's consent screen lets a user grant some requested
 * scopes and decline others, and the callback still succeeds. Storing a
 * connection whose granted scopes do not include `webmasters.readonly` produces a
 * connection that authenticates perfectly and 403s on every read — which this
 * codebase would then report as `property_forbidden`, sending the owner to check
 * Search Console permissions that are entirely correct. So the grant is checked
 * against what was asked for, and a partial grant is refused at the door.
 *
 * **A missing refresh token.** `access_type=offline` and `prompt=consent` are what
 * cause one to be issued; if it is absent anyway the connection lasts about an
 * hour and then dies with no way back except the owner noticing. A sync that runs
 * daily and unattended cannot live on that, and a connection that looks
 * successful today and is silently dead tomorrow is worse than a refusal now.
 */
final class SearchConsoleConnectController extends Controller
{
    public function __construct(
        private readonly TokenService $tokens,
    ) {}

    /**
     * Send the owner to Google's consent screen.
     *
     * ⛔ **THE ROLE GATE, AND IT WAS MISSING UNTIL 6441.**
     * `OauthConnectionPolicy` exists to answer *"who may hand the platform a
     * credential that acts as the business?"* and had no call site anywhere in
     * `app/` — so the rule it states was true only of the file stating it.
     * `UserRole::canManageConnections()` is narrower than
     * `canConfigureAutomation()` on purpose and excludes a **manager** as well
     * as `staff`; 2965 records the argument for keeping the two apart.
     *
     * ⚠️ **BOTH ENTRY POINTS ARE GATED AND NEITHER IS THE OTHER'S OUTER GUARD**
     * (398). `callback` is a plain named GET route that anyone signed in can
     * request directly, so a gate only on `redirect` would be a gate on the
     * polite path. What this one adds is that nobody is sent to Google to
     * approve a grant we would then refuse to store — a consent screen followed
     * by a 403 reads as our failure rather than as their permissions.
     *
     * ⛔ **AND THE ROLE GATE DID NOT DELIVER THAT PROMISE — 9148, 9150.** A
     * tenantless `super_admin` passes `canManageConnections()`, so this method
     * answered them **302 to Google**, and `callback()` then answered the
     * returning consent with a 500. That is precisely *"sent to Google to
     * approve a grant we would then refuse to store"*, written down two
     * paragraphs above the code that did it — and the grant is real by then:
     * Google has recorded it whether or not we store a row. **The tenancy guard
     * is what makes the sentence above true**, and it belongs here rather than
     * only on the callback for the reason the sentence gives.
     */
    public function redirect(): RedirectResponse
    {
        // See the paragraph above: refusing here is what stops a real Google
        // grant being created for a reader we cannot store one for.
        abort_if(Tenancy::id() === null, 403);

        Gate::authorize('create', OauthConnection::class);

        /** @var AbstractProvider $socialite */
        $socialite = Socialite::driver('google');

        return $socialite
            ->setScopes(self::scopes())
            ->with(self::parameters())
            ->redirectUrl(route('gsc.connect.callback'))
            ->redirect();
    }

    /**
     * Come back from Google and store the grant.
     *
     * ⛔ **THE GATE IS HERE TOO, AND THIS IS THE ONE THAT MATTERS** — see
     * `redirect()`. This method is what writes an `OauthConnection`, and it is
     * reachable without ever visiting `redirect()`.
     *
     * ⚠️ **BEFORE THE TENANT LOOKUP, DELIBERATELY.** `Tenancy::idOrFail()`
     * would throw for a signed-in user with no business — a 500 — and a person
     * who may not connect an account should be told that rather than shown a
     * broken page.
     *
     * ⛔ **AND THAT ORDERING DID NOT SAVE ANYBODY, BECAUSE THE GATE DOES NOT
     * REFUSE THE POPULATION THE PARAGRAPH IS ABOUT — 9148.** The sentence above
     * names the 500 exactly and then relies on `Gate::authorize` to prevent it;
     * `UserRole::canManageConnections()` returns **true** for `SuperAdmin`, who
     * is the entire tenantless population by design. So this route answered a
     * platform-staff callback with the very 500 its own docblock describes.
     * `CLAUDE.md`'s 314–316 in its sharpest form: **the paragraph naming the
     * hazard is what stopped anybody reading the line beside it.**
     *
     * ⚠️ **A 403 RATHER THAN `back()`'s REDIRECT, AND THAT IS NOT THE MAJORITY
     * SHAPE BEING COPIED** (9149). Every other refusal in this method redirects
     * to `account.connections` with a toast, which is right for an owner whose
     * consent went wrong. It is wrong here: `Account\Connections::render()`
     * aborts 403 for a reader with no tenant, so the redirect spends a hop to
     * reach the same status with the message discarded on the way — a dead end
     * dressed as a way out.
     */
    public function callback(Request $request): RedirectResponse
    {
        // ⛔ Before the gate and before the lookup — see the docblock. This is
        // the refusal the paragraph above claimed the gate was making.
        abort_if(Tenancy::id() === null, 403);

        Gate::authorize('create', OauthConnection::class);

        // The tenant is resolved by now; a missing row is a business deleted
        // mid-request, which nothing does.
        $business = Business::findOrFail(Tenancy::idOrFail());

        try {
            /** @var AbstractProvider $socialite */
            $socialite = Socialite::driver('google');

            $identity = $socialite->redirectUrl(route('gsc.connect.callback'))->user();
        } catch (InvalidStateException) {
            // A stale tab, a session that expired mid-flow, or a forged callback.
            // Indistinguishable from here, and all three end the same way.
            return $this->back('That connection did not complete. Please try again.');
        }

        // ⚠️ Narrowed to Socialite's OAuth2 user, not its `Contracts\User`. The
        // contract declares `getId`/`getEmail`/`getName` and **no tokens at all**
        // — `token`, `refreshToken`, `expiresIn` and `approvedScopes` are
        // properties of the concrete OAuth2 user. Sign-in gets away with the
        // contract because it deliberately discards the tokens (see
        // OauthLoginController); connecting cannot, and a driver that returned
        // anything else would have nothing to store.
        if (! $identity instanceof OauthTwoUser) {
            return $this->back('That connection did not complete. Please try again.');
        }

        $granted = self::grantedScopes($identity);

        foreach (self::scopes() as $required) {
            if (! in_array($required, $granted, true)) {
                return $this->back(
                    'Google did not give us permission to read your Search Console data. '
                    .'Please try again and leave that permission ticked.'
                );
            }
        }

        $refreshToken = self::text($identity->refreshToken);

        if ($refreshToken === null) {
            return $this->back(
                'Google did not give us a lasting connection. Please try again — if it keeps '
                .'happening, remove this app from your Google account permissions first.'
            );
        }

        $accessToken = self::text($identity->token);

        if ($accessToken === null) {
            return $this->back('That connection did not complete. Please try again.');
        }

        $expiresIn = self::seconds($identity->expiresIn);

        $this->tokens->store(
            business: $business,
            provider: OauthProvider::Gsc,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: $expiresIn === null ? null : Carbon::now()->addSeconds($expiresIn),
            // The Google account id, not an email and not a site property. It is
            // what makes `store()`'s (business, provider, external account) match
            // clause replace a reconnection rather than accumulate rows.
            externalAccountId: $identity->getId(),
            // Shown to the owner so they can tell which Google account this is
            // when they have three. An email address of a *staff user*, never an
            // end customer's — the same category `oauth_connections` already
            // holds for sign-in.
            displayLabel: $identity->getEmail(),
            scopes: $granted,
        );

        Toaster::success('Search Console is connected. Choose which site to measure below.');

        return redirect()->route('account.connections');
    }

    /**
     * @return list<string>
     */
    private static function scopes(): array
    {
        /** @var list<string> $scopes */
        $scopes = config('oauth.providers.'.OauthProvider::Gsc->value.'.scopes', []);

        return $scopes;
    }

    /**
     * @return array<string, string>
     */
    private static function parameters(): array
    {
        /** @var array<string, string> $parameters */
        $parameters = config('oauth.providers.'.OauthProvider::Gsc->value.'.authorize_parameters', []);

        return $parameters;
    }

    /**
     * What Google actually granted, as a list.
     *
     * ⚠️ Read defensively: an absent or unreadable value is treated as **nothing
     * granted**, which fails the scope check above rather than passing it.
     * Fail-closed, because the failure this guards against is a connection that
     * looks healthy and 403s forever.
     *
     * @return list<string>
     */
    private static function grantedScopes(OauthTwoUser $identity): array
    {
        return self::stringList($identity->approvedScopes);
    }

    /*
    |--------------------------------------------------------------------------
    | Three `mixed` normalisers, and why they are not paranoia
    |--------------------------------------------------------------------------
    |
    | ⚠️ SOCIALITE'S OWN DOCBLOCKS OVERSTATE ITS TYPES, AND STATIC ANALYSIS
    | BELIEVES THEM. `Laravel\Socialite\Two\User` declares `@var string` on
    | `$refreshToken` — while `AbstractProvider::userFromToken()` populates it
    | with `Arr::get($response, 'refresh_token')`, which is **null** whenever
    | Google omits one. `$expiresIn` (`@var int`) and `$approvedScopes`
    | (`@var array`) come from the same `Arr::get` calls and are null in exactly
    | the same circumstances.
    |
    | So the property is nullable in fact and non-nullable in type, and PHPStan —
    | correctly, given what it was told — reports every runtime guard against
    | that as dead code. Taking `mixed` is what lets the guard exist and be
    | analysed honestly: nothing is cast, nothing is asserted, nothing is
    | suppressed, and the check that matters keeps running.
    |
    | Getting this wrong is not cosmetic. A null refresh token reaching
    | `TokenService::store()` is a `Crypt::encryptString(null)` TypeError inside
    | the callback, after Google has already recorded the grant — the owner sees
    | a crash, and re-authorising will not produce a second refresh token unless
    | they first revoke the app in their Google account.
    */

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function seconds(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    /**
     * Land the owner somewhere they can read the message.
     *
     * ⚠️ `account.connections` and NOT back to `gsc.connect.redirect`, which
     * would bounce them straight to Google again and lose the error on the way —
     * an infinite consent loop that reads to the owner as Google being broken.
     *
     * ⛔ **NEITHER SUCCESS NOR ANY OF THE FOUR REFUSALS BELOW USED TO LAND
     * ANYWHERE THAT READ THEM** (wave 38, lane B). This redirected to
     * `account.settings` with `->withErrors(['gsc' => $message])`, and nothing
     * on that layout ever rendered `$errors` or `session('status')` — every
     * outcome of this flow, success included, was silently swallowed.
     * `Account\Connections` is where Search Console's own door now lives
     * ({@see App\Services\Visibility\SearchConsoleProperties}'s docblock), so a
     * toast here is a toast the owner is standing on the page to read —
     * `Http\Controllers\Gbp\GbpConnectController`'s pattern, matched rather than
     * reinvented.
     */
    private function back(string $message): RedirectResponse
    {
        Toaster::error($message);

        return redirect()->route('account.connections');
    }
}
