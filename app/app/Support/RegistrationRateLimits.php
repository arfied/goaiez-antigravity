<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Billing\TrialEligibility;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The velocity limit on registration itself (decision 2066, "velocity limits").
 *
 * ⛔ **THERE ARE TWO PROVISIONING DOORS AND THIS CLASS GUARDED ONE OF THEM FOR
 * THE LENGTH OF ONE REVIEW.** The first version of this docblock said
 * *"registration was the one unthrottled write path in this application"* and
 * decision 3225 recorded the same as a ruling. **Both were false when written**
 * — `OauthLoginController::findOrCreate()` calls the same
 * `TenantProvisioner::provision()` on first SSO sign-in, and its route group
 * carries `guest` and nothing else. That is 314–316's shape ("a protection layer
 * asserted before it is true") **inside the slice whose own tests quote it**,
 * which is exactly what `CLAUDE.md` predicts of it. Corrected in code and at
 * decision 3233; 3225 is history and is not edited.
 *
 * ⚠️ **SO THE LIST OF CALLERS IS THE LOAD-BEARING PART OF THIS PARAGRAPH, NOT
 * THE CLAIM ABOUT WHAT IS UNGUARDED.** Both doors are enforced now:
 *
 *   `CreateNewUser`                      `POST /register`
 *   `OauthLoginController::findOrCreate` the new-user branch only
 *
 * The other ways into this application create no tenant and are deliberately
 * absent: the magic link signs in an **existing** user only and is bounded by
 * {@see MagicLinkRateLimits} — ⚠️ **it said `throttle:5,1` until 2026-08-25 and
 * that was three hundred an hour on the raw address (9720)** — passkeys are
 * enrolment for somebody already signed in, and `StaffDirectory` creates
 * internal staff who own no business. **Anything that
 * comes to call `TenantProvisioner::provision()` belongs on the list above** —
 * that is the rule, rather than "registration is the only door", which is the
 * sentence that was wrong.
 *
 * `FortifyServiceProvider` registers limiters for `login`, `two-factor` and
 * `passkeys`, and Fortify offers no hook for the register route at all — so the
 * endpoint that creates a `User`, a `Business`, a `Location`, a feedback page, a
 * widget key, a subscription row **and claims a phone number out of the platform
 * pool** could be called as fast as a script could POST. Checked rather than
 * assumed: `config/fortify.php`'s `limiters` array has no `register` key and
 * nothing in `routes/` throttles either route.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE KEY IS THE CLIENT ADDRESS, AND NOTHING CONFIGURES TRUSTED PROXIES
 * ---------------------------------------------------------------------------
 *
 * There is no `trustProxies` anywhere in `bootstrap/`, `app/` or `config/`, so
 * `X-Forwarded-For` is ignored and this key **cannot be spoofed** — which is the
 * half that matters today and the reason this is recorded rather than changed.
 *
 * ⚠️ **The other half is a silent, total and irreversible failure the day a
 * proxy or CDN is put in front of this application** (3235). Production is a
 * cPanel box and this organisation already uses Cloudflare. Behind one,
 * `$request->ip()` becomes the proxy's address for every visitor: this limiter
 * collapses to three registrations per hour **platform-wide**, and every
 * `trial_claims` signup-origin row written meanwhile shares one fingerprint —
 * so `TrialEligibility` refuses every tenant for velocity, **permanently**,
 * because those rows are append-only and survive fixing the proxy configuration.
 * `.claude/skills/deploying/` carries the check.
 *
 * ⚠️ **THE NUMBER-POOL CLAIM IS WHY THIS IS NOT MERELY TIDY.**
 * `TenantProvisioner` calls `TenantNumbers::claimForTenant()`, so every completed
 * registration consumes an Infobip number from a finite inventory. An
 * unthrottled register endpoint is an inventory-exhaustion path with a real bill
 * attached, and it reaches that state long before anybody notices the accounts.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS KEYS ON A HASH AND NOT ON `$request->ip()`
 * ---------------------------------------------------------------------------
 *
 * `PublicAuditRateLimits`' argument, word for word: Laravel hashes a limiter key
 * before it reaches the cache, but unsalted, and the IPv4 space is 2^32 — so an
 * unsalted digest of an address is not a pseudonym, it is the address with an
 * extra step. `HashedIp` is keyed with the application key.
 *
 * ⚠️ **THE `login` LIMITER STILL KEYS ON THE RAW ADDRESS.** `PublicAuditRateLimits`
 * noted that in slice F and it is still true, still out of scope, and noted again
 * here rather than silently left — the next person to read this file is the
 * person who should fix it.
 *
 * ✅ **CLOSED 2026-08-25 (9722), AND THE PARAGRAPH ABOVE IS KEPT BECAUSE IT IS
 * THE RECORD OF HOW LONG A POINTER CAN SIT UNFOLLOWED.** Two lanes read it and
 * left it — slice F wrote it, wave 29's lane C read it and deferred with a
 * reason (9629c) — and it was the third reader who moved it. `login` and
 * `passkeys` both key on {@see HashedIp} now, granularity unchanged in both;
 * `FortifyServiceProvider` carries the argument. ⛔ **What is NOT closed is the
 * spraying gap, which is a different subject that this pointer never named**:
 * `login`'s bucket is per-credential on purpose, so an attacker rotating the
 * email field gets a fresh bucket per address, and an outer per-address ceiling
 * — this file's own 9623 repair — cannot separate a sprayer from a large
 * tenant's office, because spraying is defined by being low volume per address.
 * That one is the owner's (9723), not the next reader's.
 *
 * ---------------------------------------------------------------------------
 * WHY IT IS ENFORCED IN `CreateNewUser` AND NOT AS ROUTE MIDDLEWARE
 * ---------------------------------------------------------------------------
 *
 * Fortify owns the register route and exposes no limiter for it, so the choices
 * were to re-declare the route ourselves or to enforce inside the action Fortify
 * already calls. The action wins twice: re-declaring the route means owning
 * Fortify's middleware stack and redirect contract forever, and — the reason that
 * decides it — `CLAUDE.md` records "an outer guard refuses first, so the inner one
 * is unfalsifiable" (398) as a recurring failure. A limit inside the action can be
 * driven directly by a test; one in middleware refuses before the action runs and
 * proves nothing about the action.
 *
 * ---------------------------------------------------------------------------
 * ⛔ AND THAT PLACEMENT LEFT A HOLE THE ARGUMENT ABOVE CANNOT SEE (9623)
 * ---------------------------------------------------------------------------
 *
 * ⚠️ **THE ARGUMENT IS UNCHANGED AND IS STILL RIGHT. IT IS ALSO INCOMPLETE.**
 * `CreateNewUser::create()` calls `Validator::…->validate()` before it calls
 * `enforce()`, and that validator carries `Rule::unique(User::class)` — so a
 * request whose only purpose is to ask *"does this address already have an
 * account?"* throws at validation and **never reaches line 161**. Measured, not
 * read: six consecutive posts of a duplicate address at `POST /register` all
 * answered *"The email has already been taken."* and the limiter below was
 * never hit once. **Account creation was throttled; address probing was not**,
 * and the two are the same endpoint.
 *
 * ⛔ **MOVING `enforce()` ABOVE THE VALIDATOR IS THE FIX THAT LOOKS RIGHT AND
 * IS REFUSED.** It is the exact ordering the paragraphs above and
 * `CreateNewUser`'s own comment argue against — somebody fumbling a password
 * rule three times is not an attacker — and it converts this limit from *three
 * accounts an hour* into *three form submissions an hour*, which is a lockout
 * on a person's own first minute.
 *
 * ✅ **WHAT CLOSES IT IS A SECOND LIMIT AT A DIFFERENT GRANULARITY**, on the
 * route, bounding **requests** where {@see self::PER_HOUR} bounds
 * **accounts**: {@see self::REQUESTS_PER_HOUR} and {@see self::REQUEST_LIMITER}
 * below. ⚠️ **398 IS ANSWERED BY THE ARITHMETIC RATHER THAN BY THE PLACEMENT**:
 * the outer ceiling is ten times the inner one, so a caller who is genuinely
 * registering still meets {@see self::PER_HOUR} first and the inner limit stays
 * falsifiable at the route as well as in the action. An outer guard sized *at
 * or below* the inner one would have made this file's own tests vacuous.
 *
 * ⛔ **AND IT DOES NOT CLOSE THE ORACLE. IT BOUNDS THE RATE.** `Rule::unique`
 * is deliberate — a signup form that cannot say *"you already have an
 * account"* is a worse product (9506) — so the answer is still there, at
 * thirty questions an hour per network instead of as fast as a script can post.
 * **Whether that message changes at all is the owner's** and is raised at 9628.
 */
final class RegistrationRateLimits
{
    /**
     * The named limiter, used as the cache key prefix.
     */
    public const string LIMITER = 'register';

    /**
     * How many accounts one network may create in an hour.
     *
     * ⚠️ **AN ENGINEERING RAIL, MINE TO SET** — T137 §3 puts rate limiting under
     * "no owner decisions needed", and unlike an allowance or a price nothing
     * about this figure is a promise to a customer.
     *
     * Three, matching `PublicAuditRateLimits::CREATE_PER_HOUR`, and the sizing
     * argument is the stronger one here: a *free audit* is something one visitor
     * legitimately runs several times, whereas a person registers once. Three an
     * hour leaves a shared office and a demo laptop entirely alone and stops a
     * script dead.
     *
     * ⚠️ **IT REFUSES A BURST AND NEVER A PERSON.** An hour later the same
     * network registers again. That is the whole difference between this and
     * {@see TrialEligibility}'s durable signals, which
     * withhold an allowance and are recoverable by a support operator rather than
     * by waiting.
     */
    public const int PER_HOUR = 3;

    /**
     * The named limiter on the route, used by `routes/web.php`.
     *
     * ⚠️ **A DIFFERENT NAME FROM {@see self::LIMITER} ON PURPOSE.** That
     * constant is the cache-key prefix for the in-action limit and this one is
     * a `RateLimiter::for()` name; they count different things and must not
     * share a bucket, and two controls wearing one name is how somebody later
     * "removes the duplicate".
     */
    public const string REQUEST_LIMITER = 'register-request';

    /**
     * How many times one network may POST to the registration endpoint in an
     * hour, whatever the outcome.
     *
     * ⚠️ **AN ENGINEERING RAIL, MINE TO SET**, exactly as {@see self::PER_HOUR}
     * is — T137 §3 puts rate limiting under "no owner decisions needed".
     *
     * Thirty, and the figure is chosen by the gap rather than by the absolute:
     * it is **ten times** {@see self::PER_HOUR}, which is what keeps the inner
     * limit the one a genuine registration meets first (398, and see the class
     * docblock). A person filling in one form and correcting it several times
     * uses a handful; a shared office where four colleagues each fumble twice
     * uses twelve. A script walking an address list uses thirty in the first
     * second and then stops for an hour.
     *
     * ⚠️ **IT CAN REFUSE A PERSON AND {@see self::PER_HOUR} CLAIMS IT NEVER
     * DOES.** Behind a large NAT the thirty-first submission in an hour is
     * refused. That is the accepted trade, written down rather than glossed —
     * and the proxy paragraph above is the version of it that is not
     * acceptable, because behind an unconfigured CDN this collapses to thirty
     * signup attempts an hour for the entire platform.
     */
    public const int REQUESTS_PER_HOUR = 30;

    /**
     * Register the route-level ceiling.
     *
     * ⚠️ **THE ONLY LIMIT IN THIS FILE THAT IS NOT ENFORCED FROM AN ACTION**,
     * and the reason it cannot be is the whole of the hole it closes: the
     * request it has to refuse is one that throws in the validator two dozen
     * lines before any code of ours runs.
     */
    public static function register(): void
    {
        RateLimiter::for(self::REQUEST_LIMITER, fn (Request $request): Limit => Limit::perHour(self::REQUESTS_PER_HOUR)
            ->by(self::key($request))
            ->response(self::refusal()));
    }

    /**
     * @return callable(): Response
     */
    private static function refusal(): callable
    {
        // Deliberately vague about the mechanism, on `enforce()`'s reasoning
        // one method down: a message naming the limit or the window tells a
        // script how long to sleep.
        return fn (): Response => response(
            'Too many requests. Try again shortly.',
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }

    /**
     * Refuse if this network has already registered its share this hour.
     *
     * ⚠️ **CHECK AND HIT ARE ONE CALL BECAUSE A CALLER WHO FORGETS THE SECOND
     * BUILDS A LIMITER THAT NEVER FILLS.** That failure is silent and total: the
     * suite passes, the endpoint is unlimited, and nothing anywhere says so.
     *
     * ⚠️ **CALLED AFTER VALIDATION, DELIBERATELY** — see `CreateNewUser`. Somebody
     * fumbling a password rule three times is not an attacker, and locking them
     * out of the product for an hour on their own first minute is the support
     * ticket this control is supposed to prevent, not cause.
     *
     * @throws ValidationException
     */
    public static function enforce(Request $request): void
    {
        $key = self::LIMITER.':'.self::key($request);

        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            throw ValidationException::withMessages([
                // Outcome language, and deliberately vague about the mechanism:
                // a message naming the limit or the window tells a script how
                // long to sleep. `22`'s rule is that a string names what the
                // person controls — here, trying again later or asking us.
                'email' => 'We could not open an account just now. Try again a little later, '
                    .'or get in touch and we will set it up with you.',
            ])->status(429);
        }

        RateLimiter::hit($key, 3600);
    }

    /**
     * A per-visitor key that is not an address.
     *
     * The fallback is `PublicAuditRateLimits`' exactly: a request with no
     * resolvable client address gets one shared bucket rather than a free pass,
     * which makes the limit *stricter* for an unusual case. A null key would be an
     * unlimited endpoint for anyone who can arrange to have no address.
     */
    private static function key(Request $request): string
    {
        return HashedIp::of($request) ?? 'register:unknown-origin';
    }
}
