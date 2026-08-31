<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The limiter guarding the one-click unsubscribe endpoint — T176 P21.
 *
 * ⚠️ **THE THING BEING PROTECTED IS THE DATABASE, NOT THE TOKEN.** A token
 * cannot be guessed — forging one needs `APP_KEY` — so unlike
 * `ShortLinkRateLimits` there is no token space to walk and no miss budget worth
 * keeping. What a public POST endpoint can still be made to do is decrypt and
 * write in a loop, and this is what stops that.
 *
 * ⚠️ **GENEROUS, BECAUSE A REAL CLIENT MAY POST MORE THAN ONCE.** RFC 8058 §3.2
 * expects the sender to tolerate retries: a mail client that does not see a 2xx
 * will try again, and some post from a shared provider address on the
 * recipient's behalf — so several unsubscribes a minute from one apparent
 * visitor is an ordinary Monday and not an attack. Refusing a genuine opt-out to
 * slow somebody down is the wrong trade in both directions: the person keeps
 * getting mail, and we keep sending it.
 *
 * Keyed on `HashedIp`, never a raw address — `CLAUDE.md`, and the same helper
 * every other public limit in this application uses.
 */
final class UnsubscribeRateLimits
{
    /**
     * Unsubscribe requests allowed per visitor per minute.
     */
    public const int PER_MINUTE = 60;

    public static function register(): void
    {
        RateLimiter::for('mail-unsubscribe', fn (Request $request): Limit => Limit::perMinute(self::PER_MINUTE)
            ->by(self::key($request))
            ->response(self::refusal()));
    }

    /**
     * A per-visitor key that is not an address.
     *
     * The fallback gives a request with no resolvable client address one shared
     * bucket rather than a free pass — `LegalDocumentRateLimits`' rule, and the
     * right direction to fail for an unusual case.
     */
    private static function key(Request $request): string
    {
        return HashedIp::of($request) ?? 'mail-unsubscribe:unknown-origin';
    }

    /**
     * @return callable(): Response
     */
    private static function refusal(): callable
    {
        return fn (): Response => response(
            'Too many requests. Try again shortly.',
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }
}
