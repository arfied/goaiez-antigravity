<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\ShortLinks\FetchClassifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public-endpoint protection for the redirector — T137 §3.4.
 *
 * *"Rate-limit + bot-filter /f/{slug} and the short-link redirector so stats stay
 * clean and abuse is contained."* The bot filter is {@see FetchClassifier};
 * this is the other half.
 *
 * ## What the limit is actually for, which is not what it looks like
 *
 * ⚠️ **A REAL PERSON OPENS A LINK ONCE, AND THE LIMIT IS NOT ABOUT THEM.** It is
 * about somebody walking the token space. `short_links` carries `public_read`,
 * so the database will serve any row whose token is presented — the token's 71
 * bits are what make that safe, and a rate limit is what keeps those bits from
 * being spent by a script that has all week.
 *
 * ⛔ **AND A MISS IS THE INTERESTING EVENT, NOT A HIT.** A person's link resolves;
 * a guesser's does not. So the tight limit is on **misses**, per visitor, across
 * every token they try — which is the property `FeedbackRateLimits` records
 * having needed for exactly the same reason (its `MISS_PER_HOUR`, and its note
 * that a per-page limit cannot provide it). A generous limit on hits sits beside
 * it so that a shared office NAT opening a campaign's links is never refused.
 *
 * Keyed on `HashedIp`, never a raw address — `CLAUDE.md`, and the same helper
 * every other public limit in this application uses.
 */
final class ShortLinkRateLimits
{
    /**
     * Resolutions allowed per visitor per minute.
     *
     * Generous on purpose. Behind one NAT there may be a whole office opening
     * the same campaign, and refusing a real customer's redirect to slow a
     * guesser down is the wrong trade — the miss limit below is the one doing
     * the work.
     */
    public const int RESOLVE_PER_MINUTE = 60;

    /**
     * Misses allowed per visitor per hour.
     *
     * A real person does not miss: their link came out of a message we sent
     * them. A stale link from an old message is the one honest miss, and it is
     * rare. Twenty an hour applies across every token the visitor tries, so a
     * script guessing distinct tokens is capped regardless of how many it
     * spreads them over — `FeedbackRateLimits::MISS_PER_HOUR`'s reasoning and
     * its number, because the two endpoints face the same guesser.
     */
    public const int MISS_PER_HOUR = 20;

    public static function register(): void
    {
        RateLimiter::for('short-link', fn (Request $request): Limit => Limit::perMinute(self::RESOLVE_PER_MINUTE)
            ->by(self::visitorKey($request))
            ->response(self::refusal()));
    }

    /**
     * Whether this visitor has spent their misses for the hour.
     *
     * ⚠️ **DELIBERATELY NOT A NAMED `RateLimiter::for()` LIMIT BEHIND A ROUTE**,
     * for `FeedbackRateLimits`' reason: a route-level limiter counts every
     * request, and what needs counting is only the ones that failed to resolve.
     * The controller calls this after the lookup.
     */
    public static function missesExhausted(Request $request): bool
    {
        return RateLimiter::tooManyAttempts(self::missKey($request), self::MISS_PER_HOUR);
    }

    /**
     * Record that this visitor presented a token that resolved to nothing.
     */
    public static function recordMiss(Request $request): void
    {
        RateLimiter::hit(self::missKey($request), 3_600);
    }

    private static function missKey(Request $request): string
    {
        return 'short-link-miss:'.self::visitorKey($request);
    }

    private static function visitorKey(Request $request): string
    {
        return HashedIp::of($request) ?? 'short-link:unknown-origin';
    }

    /**
     * @return callable(): Response
     */
    private static function refusal(): callable
    {
        // ⚠️ **NO BODY AND NO EXPLANATION.** Every other public surface in this
        // application answers a person who can read; this one answers a script.
        // A message naming the limit is a message telling a guesser how to pace
        // themselves.
        return fn (): Response => response('', Response::HTTP_TOO_MANY_REQUESTS);
    }
}
