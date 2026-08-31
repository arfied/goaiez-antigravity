<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The three named limiters guarding the free instant audit.
 *
 * `29` §6.2 specifies one of them — "rate limit 3/hr/IP" — and the other two
 * exist because the same endpoint family has two other verbs with completely
 * different economics. Sizing all three the same would either throttle a
 * polling client that costs nothing or leave a billable one wide open.
 *
 * ---------------------------------------------------------------------------
 * WHY THESE KEY ON A HASH AND NOT ON `$request->ip()`
 * ---------------------------------------------------------------------------
 *
 * Laravel does hash the limiter key before it reaches the cache — ThrottleRequests
 * runs it through sha1, and the named-limiter path through md5. Neither is
 * salted. The IPv4 space is 2^32, so an unsalted digest of an address is not a
 * pseudonym: anyone holding the cache can enumerate the whole space in minutes
 * and recover every address that hit the endpoint.
 *
 * That matters here more than on an authenticated route, because Redis holding
 * "which addresses ran a free audit in the last hour" is a log of visitor IPs by
 * another name, and `29`'s privacy rule against storing raw IP is about the
 * address being a personal identifier rather than about which column it sits in.
 * HashedIp is keyed with the application key, so the same enumeration produces
 * nothing.
 *
 * The Fortify `login` limiter still keys on the raw address. That predates this
 * and is out of slice F's scope — noted here rather than silently left, because
 * the next person to read this file is exactly the person who should fix it.
 */
final class PublicAuditRateLimits
{
    /**
     * `29` §6.2. Each of these can spend around 9c of Google's meter.
     */
    public const int CREATE_PER_HOUR = 3;

    /**
     * Typing is bursty and each request costs 0.283c. Thirty a minute is a few
     * searches at a human typing speed and nowhere near enough to farm.
     */
    public const int SUGGEST_PER_MINUTE = 30;

    /**
     * Polling costs nothing but a row read. The reference client polls every
     * 900ms for up to the 20s audit budget, so ~22 per audit; 120 leaves room
     * for a few tabs without leaving the endpoint unbounded.
     */
    public const int POLL_PER_MINUTE = 120;

    public static function register(): void
    {
        RateLimiter::for('public-audit-create', fn (Request $request): Limit => Limit::perHour(self::CREATE_PER_HOUR)
            ->by(self::key($request))
            ->response(self::refusal(
                'That is three audits this hour. Try again a little later, or start free to run as many as you like.',
            )));

        RateLimiter::for('public-audit-suggest', fn (Request $request): Limit => Limit::perMinute(self::SUGGEST_PER_MINUTE)
            ->by(self::key($request))
            // Suggestions failing is never an error on this page — an empty
            // list is the honest degradation, and the input still works.
            ->response(fn (): Response => response()->json(['suggestions' => []])));

        RateLimiter::for('public-audit-poll', fn (Request $request): Limit => Limit::perMinute(self::POLL_PER_MINUTE)
            ->by(self::key($request))
            ->response(self::refusal('Checking too often. Wait a moment and refresh.')));
    }

    /**
     * A per-visitor key that is not an address.
     *
     * The fallback matters: a request with no resolvable client address gets one
     * shared bucket rather than a free pass. That makes the *limit* stricter for
     * an unusual case, which is the right direction to fail — the alternative,
     * a null key, is an unlimited endpoint for anyone who can arrange to have no
     * address.
     */
    private static function key(Request $request): string
    {
        return HashedIp::of($request) ?? 'public-audit:unknown-origin';
    }

    /**
     * @return callable(): Response
     */
    private static function refusal(string $message): callable
    {
        return fn (): Response => response()->json(
            ['message' => $message],
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }
}
