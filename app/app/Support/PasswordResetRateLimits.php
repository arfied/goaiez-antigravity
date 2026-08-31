<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Responses\PasswordResetFailedResponse;
use App\Http\Responses\PasswordResetLinkRequestedResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two limiters on the password-reset half of the auth surface (9505, 9620).
 *
 * ⛔ **BOTH ROUTES WERE UNAUTHENTICATED AND UNTHROTTLED FOR THE LIFE OF THIS
 * APPLICATION, AND NOTHING IN THE TREE SAID SO.** `config/fortify.php` enables
 * `Features::resetPasswords()`, which registers `POST /forgot-password` and
 * `POST /reset-password`; Fortify reads a limiter for `login`, `two-factor`,
 * `passkeys` and `verification` and **for neither of these**, so both shipped on
 * `['web', 'guest:web']` and nothing else. Measured at the route rather than
 * read: `gatherMiddleware()` on both was exactly `web,guest:web` while
 * `POST /login` carried `throttle:login`.
 *
 * ⚠️ **THE TWO ARE NOT ONE CONTROL WITH TWO NAMES.** They defend different
 * things and would be sized differently even if the numbers had come out equal:
 *
 *   `password-reset-request`  a stranger causes MAIL to be sent to an address
 *                             they chose — `routes/web.php`'s own words about
 *                             the magic link, *"without a limit it is a mail
 *                             cannon pointed at anyone whose address is
 *                             guessed"* — and every send spends the platform's
 *                             SES quota and reputation, neither of which
 *                             belongs to the person being mailed
 *
 *   `password-reset-attempt`  a stranger causes a BCRYPT VERIFY. Laravel's
 *                             `DatabaseTokenRepository::exists()` is
 *                             `$this->hasher->check($token, $record['token'])`,
 *                             and it runs **before** the password rules are
 *                             validated — so an unthrottled endpoint hands
 *                             anybody an unbounded CPU cost per request, on a
 *                             box that also serves every tenant's console
 *
 * ⛔ **NEITHER FIGURE IS THE ORACLE'S REMEDY AND NEITHER MAY BE SOLD AS ONE.**
 * The enumeration answer at both doors is a *message*, and the messages are
 * closed by {@see PasswordResetLinkRequestedResponse} and
 * {@see PasswordResetFailedResponse}. A rate limit bounds
 * how fast a stranger asks; it never changes what the answer says. Reading
 * these limits as the fix for 9505 is the shape `CLAUDE.md` records as *a
 * protection layer asserted before it is true*.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE KEY IS `HashedIp`, AND NOTHING CONFIGURES TRUSTED PROXIES
 * ---------------------------------------------------------------------------
 *
 * `PublicAuditRateLimits`' argument, unchanged and repeated here because it is
 * the reason this class exists rather than a bare `throttle:5,1`: Laravel hashes
 * a limiter key before it reaches the cache but **unsalted** — `ThrottleRequests`
 * stores `md5($limiterName.$limit->key)` — and the IPv4 space is 2^32, so an
 * unsalted digest of an address is the address with an extra step. `HashedIp`
 * is keyed with `APP_KEY`.
 *
 * ⚠️ **AND `RegistrationRateLimits`' PROXY WARNING APPLIES WORD FOR WORD.**
 * There is no `trustProxies` configuration anywhere in `bootstrap/`, `app/` or
 * `config/`, so `X-Forwarded-For` is ignored and this key cannot be spoofed —
 * and the day a CDN is put in front of this application, `$request->ip()`
 * becomes the proxy's address for every visitor and **both limits below collapse
 * to a platform-wide budget**. `.claude/skills/deploying/` carries the check.
 * ⚠️ **The failure here is milder than the one on the registration door** — no
 * durable row is written, so it recovers the moment the proxy is configured —
 * but it is a total lockout of password reset while it lasts.
 */
final class PasswordResetRateLimits
{
    /**
     * Reset links one network may ask for in an hour.
     *
     * ⚠️ **AN ENGINEERING RAIL, MINE TO SET** — T137 §3 puts rate limiting under
     * "no owner decisions needed", and unlike an allowance or a price nothing
     * about this figure is a promise to a customer.
     *
     * Five, and the sizing argument is *a person needs one link*. Asking twice
     * because the first mail was slow is ordinary; asking five times in an hour
     * from one network is either two colleagues having a bad morning or a
     * script, and the second is what this is for. It is deliberately more
     * generous than {@see RegistrationRateLimits::PER_HOUR} and
     * `PublicAuditRateLimits::CREATE_PER_HOUR`, which are both three, because
     * being unable to get back into an account you own is a worse outcome than
     * being unable to open a new one.
     *
     * ⚠️ **IT CAN REFUSE A PERSON, AND `RegistrationRateLimits` CLAIMS ITS OWN
     * LIMIT NEVER DOES.** Behind a large office NAT the sixth colleague in an
     * hour is refused for something they did nothing to cause. That is the
     * accepted trade and it is written down rather than glossed: the refusal
     * lasts an hour, it costs nobody an account, and the alternative is an
     * unbounded mail cannon. The proxy paragraph above is the version of this
     * that is not acceptable, and it is a deployment check rather than a figure.
     */
    public const int REQUESTS_PER_HOUR = 5;

    /**
     * Reset submissions one network may make in an hour.
     *
     * Ten, and the sizing argument is different from the one above: a genuine
     * reset is **one** submission, but the password-strength rules are checked
     * *after* the token, so a person choosing a password the rules refuse
     * spends one of these each time they try again. Five fumbles plus the
     * successful attempt is six; ten leaves room and still bounds a stranger to
     * ten bcrypt verifies an hour per network.
     *
     * ⚠️ **GUESSING THE TOKEN IS NOT WHAT THIS BOUNDS.** The token is 64
     * characters from `Str::random()` and is stored hashed, so brute force was
     * never on the table at any rate. What this bounds is the **work** — see the
     * class docblock — and a limit sized against guessing would have been
     * arbitrary, because any figure at all defeats guessing.
     */
    public const int ATTEMPTS_PER_HOUR = 10;

    /**
     * The limiter guarding `POST /forgot-password`.
     */
    public const string REQUEST_LIMITER = 'password-reset-request';

    /**
     * The limiter guarding `POST /reset-password`.
     */
    public const string ATTEMPT_LIMITER = 'password-reset-attempt';

    public static function register(): void
    {
        RateLimiter::for(self::REQUEST_LIMITER, fn (Request $request): Limit => Limit::perHour(self::REQUESTS_PER_HOUR)
            ->by(self::key($request, self::REQUEST_LIMITER))
            ->response(self::refusal()));

        RateLimiter::for(self::ATTEMPT_LIMITER, fn (Request $request): Limit => Limit::perHour(self::ATTEMPTS_PER_HOUR)
            ->by(self::key($request, self::ATTEMPT_LIMITER))
            ->response(self::refusal()));
    }

    /**
     * A per-visitor key that is not an address.
     *
     * ⛔ **THE ADDRESS ALONE, NEVER THE ADDRESS AND THE EMAIL.** The `login`
     * limiter keys on `email|ip` so that one person's failures cannot lock out
     * everybody behind their router, which is right for a credential check —
     * and it is wrong here, because a bucket per submitted address is a bucket
     * a stranger walking a list never fills. The whole population this limits
     * is *one source asking about many addresses*, so the source is the key.
     *
     * The fallback gives a request with no resolvable client address one shared
     * bucket rather than a free pass — `UnsubscribeRateLimits`' rule, and the
     * right direction to fail for an unusual case.
     */
    private static function key(Request $request, string $limiter): string
    {
        return HashedIp::of($request) ?? $limiter.':unknown-origin';
    }

    /**
     * @return callable(): Response
     */
    private static function refusal(): callable
    {
        // ⚠️ IDENTICAL ON BOTH LIMITERS AND DELIBERATELY SAYS NOTHING. A
        // refusal that named the window would tell a script how long to sleep,
        // and one that named the address would be the oracle these limits sit
        // beside. `22`'s rule is that a string names what the person controls.
        return fn (): Response => response(
            'Too many requests. Try again shortly.',
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }
}
