<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The limiter guarding `GET /legal/{doc}`.
 *
 * Every other public route in this codebase carries one — `feedback-view`,
 * `feedback-submit`, and the three `public-audit-*` limiters — and this route
 * had none. It is public, unauthenticated, and linked from the consent
 * disclosure on the hosted feedback page, so a real customer's traffic here is
 * one or two clicks, not a loop; generous is the right size, not tight.
 *
 * Keyed on a hash, never `$request->ip()`, for the reason PublicAuditRateLimits
 * sets out at length: Laravel hashes limiter keys with an unsalted digest, and
 * an unsalted digest of an IPv4 is reversible by arithmetic.
 */
final class LegalDocumentRateLimits
{
    /**
     * A person reading two documents from the same disclosure, twice each
     * after a slow connection retries, is nowhere near sixty. A script
     * walking the three-document allowlist to look for a fourth is caught
     * quickly and cheaply — this route costs a view render, nothing more.
     */
    public const int VIEW_PER_MINUTE = 60;

    public static function register(): void
    {
        RateLimiter::for('legal-document', fn (Request $request): Limit => Limit::perMinute(self::VIEW_PER_MINUTE)
            ->by(self::key($request))
            ->response(self::refusal()));
    }

    /**
     * A per-visitor key that is not an address.
     *
     * The fallback gives a request with no resolvable client address one
     * shared bucket rather than a free pass — stricter for an unusual case,
     * which is the right direction to fail.
     */
    private static function key(Request $request): string
    {
        return HashedIp::of($request) ?? 'legal-document:unknown-origin';
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
