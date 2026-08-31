<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The limiter guarding the public widget feed (`17` FPR-05).
 *
 * ⚠️ THE THING THIS PROTECTS IS NOT WHAT IT LOOKS LIKE. The feed is cached for
 * sixty seconds, so sustained traffic against one widget costs one query a
 * minute no matter how much of it arrives — the database is not the resource at
 * risk. What a limit buys here is a ceiling on the *uncached* case: a caller
 * cycling many embed keys, or hitting one immediately after each expiry.
 *
 * KEYED ON (VISITOR, WIDGET), GENEROUSLY. A widget lives on a page that real
 * people load, and this product's tenants are local businesses whose visitors
 * may share a corporate NAT or a mobile carrier's CGNAT — the same
 * shared-address problem `FeedbackRateLimits` documents at length, and the
 * reason a tight per-visitor limit is the wrong instrument. Sixty a minute is
 * far above a browser rendering one page and far below anything worth calling
 * traffic.
 *
 * NO MISS LIMITER, unlike the feedback page. That one counts failed slug
 * resolutions because its slugs carry a readable business-name stem. An
 * `embed_key` is a random UUID with nothing derived from tenant data, so
 * enumeration is not a threat a counter improves.
 *
 * KEYED ON A HASH, NEVER `$request->ip()` — Laravel hashes limiter keys with an
 * unsalted digest, and an unsalted digest of an IPv4 is reversible by
 * arithmetic. `PublicAuditRateLimits` sets out why at length.
 */
final class WidgetRateLimits
{
    public const int FEED_PER_MINUTE = 60;

    public static function register(): void
    {
        RateLimiter::for('widget-feed', fn (Request $request): Limit => Limit::perMinute(self::FEED_PER_MINUTE)
            ->by(self::widgetKey($request))
            ->response(fn (): Response => response()->json(
                ['message' => 'Too many requests.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            )));
    }

    /**
     * A per-visitor, per-widget key that is not an address.
     *
     * The fallback gives a request with no resolvable client address one shared
     * bucket rather than a free pass — stricter for an unusual case, which is
     * the right direction to fail.
     */
    private static function widgetKey(Request $request): string
    {
        $key = $request->route('embed_key');

        return (HashedIp::of($request) ?? 'widget:unknown-origin')
            .':'.(is_string($key) ? $key : 'unknown-widget');
    }
}
