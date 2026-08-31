<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The limiters guarding the hosted feedback page.
 *
 * WHY THERE IS NO CAPTCHA HERE. FPR-01's acceptance criteria include "no
 * third-party network calls fire", which rules out the Turnstile that row 2
 * slice F put in front of the public audit. It is a direct conflict between two
 * tickets in the same row, and FPR-01 wins on its own page — this is also the
 * one flow where friction costs the most, against an acceptance criterion of
 * "submits in under 30 seconds".
 *
 * So abuse control on the POST is three cheap first-party things, and this is
 * one of them. The others are a honeypot and a session-held timing floor, both
 * in StoreFeedbackRequest.
 *
 * `feedback-view` IS A SINGLE, GENEROUS PER-(VISITOR, SLUG) LIMIT, ON PURPOSE.
 * It applies to `feedback.show` and `feedback.thanks` alike, since
 * ResolveFeedbackPage 404s both identically for an unknown slug and either
 * would otherwise answer "does this slug exist?" for free. Its only job is
 * catching sustained reloading of one already-known page.
 *
 * IT USED TO ALSO CARRY A SECOND, PER-VISITOR LIMIT, AND THAT WAS WRONG TWICE
 * OVER. First: it was meant to bound enumeration, but a script walking distinct
 * candidate slugs gets a fresh (visitor, slug) bucket on every guess, so the
 * per-page limit structurally cannot see enumeration at all — no amount of
 * retuning fixes a key shape that never repeats. Second, and worse: a limit
 * that counts every *request* from one visitor fights this product's actual
 * traffic. A QR code sits on every table at a restaurant; the customers
 * scanning it share the venue's guest Wi-Fi or one mobile carrier's CGNAT, so
 * many genuine diners present as one visitor. A request-counting limit tight
 * enough to slow an enumerator starts refusing real customers partway through
 * a normal lunch service — the exact scenario this page exists to serve.
 *
 * ENUMERATION IS BOUNDED SEPARATELY, BY COUNTING MISSES RATHER THAN REQUESTS —
 * see FeedbackRateLimits::refuseIfTooManyMisses() and ResolveFeedbackPage,
 * which is where it is called. A resolution that succeeds costs nothing here,
 * however many customers share the address; almost all of an enumerator's
 * requests fail to resolve, because that is what enumeration is, so counting
 * only the failures catches it without ever touching real traffic.
 *
 * KEYED ON A HASH, NEVER `$request->ip()`, for the reason PublicAuditRateLimits
 * sets out at length: Laravel hashes limiter keys with an unsalted digest, and
 * an unsalted digest of an IPv4 is reversible by arithmetic.
 */
final class FeedbackRateLimits
{
    /**
     * A real customer submits once. A household sharing an address might submit
     * twice. Five an hour leaves room for a retry after a validation error and
     * is nowhere near enough to farm a tenant's review feed.
     */
    public const int SUBMIT_PER_HOUR = 5;

    /**
     * The floor between rendering the form and receiving it.
     *
     * Below what reading a five-point scale and tapping one takes, and far
     * above what a script needs — so it separates the two without rejecting a
     * fast human. Lives here rather than in the request class so the test and
     * the check read the same number.
     */
    public const int MINIMUM_SECONDS = 2;

    /**
     * Reloads of one already-known page, keyed on (visitor, slug).
     *
     * A person loading the form once — or twice, after fixing a typo before the
     * timing floor even applies — is nowhere near thirty requests to the same
     * slug in a minute. Generous on purpose, and reachable now that nothing
     * smaller sits above it: this limit's only job is catching sustained
     * hammering of a page whose address is already known.
     */
    public const int VIEW_PER_MINUTE = 30;

    /**
     * Resolution misses allowed per visitor, per hour.
     *
     * Per hour rather than per minute, because a miss is a rare event for a
     * real person — a stale printed sign, a mistyped link — and this needs to
     * be tight enough to matter against a script that produces almost nothing
     * but misses. Twenty an hour is generous for that rare real miss while
     * applying across every slug the visitor has tried: a script guessing
     * distinct candidates is capped at twenty misses an hour regardless of how
     * many different slugs it spreads them across, which is the property
     * `feedback-view` cannot provide (see this class's own docblock).
     */
    public const int MISS_PER_HOUR = 20;

    public static function register(): void
    {
        RateLimiter::for('feedback-submit', fn (Request $request): Limit => Limit::perHour(self::SUBMIT_PER_HOUR)
            ->by(self::pageKey($request))
            ->response(self::refusal()));

        RateLimiter::for('feedback-view', fn (Request $request): Limit => Limit::perMinute(self::VIEW_PER_MINUTE)
            ->by(self::pageKey($request))
            ->response(self::refusal()));
    }

    /**
     * Refuse a visitor who has missed too many resolutions this hour, or
     * return null so ResolveFeedbackPage can carry on to its ordinary 404.
     *
     * DELIBERATELY NOT A NAMED `RateLimiter::for()` LIMIT BEHIND A ROUTE
     * `throttle:` MIDDLEWARE. A route middleware runs unconditionally, before
     * anything downstream has decided whether this request is a hit or a
     * miss — and here, whether it counts at all depends entirely on that
     * answer. Only ResolveFeedbackPage knows it, at the moment it is about to
     * throw its 404, which is where this is called from.
     *
     * A HIT COSTS NOTHING; ONLY A MISS DOES. This is the whole fix for the
     * shared-address problem this class's own docblock describes: a resolution
     * that succeeds — the QR code on the table in front of a real customer —
     * never touches this limiter no matter how many other customers share the
     * same address, because it is never counted. Only a slug that fails to
     * resolve counts, and that is rare for anyone except a script guessing.
     */
    public static function refuseIfTooManyMisses(Request $request): ?Response
    {
        $key = 'feedback-resolve-miss:'.self::visitorKey($request);

        if (RateLimiter::tooManyAttempts($key, self::MISS_PER_HOUR)) {
            return (self::refusal())();
        }

        RateLimiter::hit($key, 3600);

        return null;
    }

    /**
     * A per-visitor, per-page key that is not an address.
     *
     * The fallback gives a request with no resolvable client address one shared
     * bucket rather than a free pass — stricter for an unusual case, which is
     * the right direction to fail.
     */
    private static function pageKey(Request $request): string
    {
        $slug = $request->route('slug');

        return self::visitorKey($request).':'.(is_string($slug) ? $slug : 'unknown-page');
    }

    /**
     * A per-visitor key with no page in it at all.
     */
    private static function visitorKey(Request $request): string
    {
        return HashedIp::of($request) ?? 'feedback:unknown-origin';
    }

    /**
     * @return callable(): Response
     */
    private static function refusal(): callable
    {
        return fn (): Response => response(
            __('feedback.errors.rate_limited'),
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }
}
