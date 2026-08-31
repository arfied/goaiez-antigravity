<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The limiter guarding `GET /api/me` (decision 3940).
 *
 * ⛔ **THIS SAID "IT WAS THE ONLY ROUTE IN THIS APPLICATION WITH NO LIMITER AT
 * ALL" AND THAT WAS FALSE WHEN IT WAS WRITTEN — CORRECTED 2026-08-25 (9620).**
 * `POST /forgot-password`, `POST /reset-password` and `POST /register` each
 * carried `['web', 'guest:web']` and no throttle for the life of the
 * application, and the whole signed-out marketing surface carries none by
 * argument. The sentence continued *"Every other route names one — the three
 * `public-audit-*`, `widget-feed`, `short-link`, `legal-document` and the two
 * `feedback-*`"*, and **a hand-written enumeration of what is protected is the
 * thing that goes stale**: at the time it listed eight of the ten it could see
 * and none of the three it could not.
 *
 * ⚠️ **THE HALF THAT WAS TRUE IS THE LOAD-BEARING HALF AND IT IS UNCHANGED:
 * `auth:sanctum` IS NOT A LIMITER.** `bootstrap/app.php` never calls
 * `$middleware->throttleApi()`, so Laravel's `api` group emits **no**
 * `throttle:` entry of its own (see
 * `Illuminate\Foundation\Configuration\Middleware::throttleApi()`), and nothing
 * in `app/` registers a limiter under that name. Authentication answers *who*
 * is asking; it says nothing about *how often*.
 *
 * ⛔ **AND THE SENTENCE ABOVE USED TO NAME THAT ABSENT LIMITER IN A SPELLING A
 * SCANNER READS AS A DECLARATION** (9626). `ObservabilityTest` §11's first draft
 * scanned raw source for `RateLimiter::for('…')` and found **seventeen**
 * limiters in an application that has sixteen — the seventeenth was this
 * paragraph. It is reworded here so the sentence still says the true thing
 * without spelling the call, and the lint reads `phpWithoutComments()` so that
 * neither this file nor any other can declare a limiter by mentioning one.
 *
 * ⚠️ **AND THE ENDPOINT NOW SERVES MONEY**, which is what moved this from tidy
 * to load-bearing: one request is ~14 database round trips, six of them the
 * `credit_ledger` head-row reads that report all six balances (3668, 3675). A
 * client that polls this to compute a burn rate — 3684 predicts exactly that —
 * is one loop away from six head-row lookups per iteration.
 *
 * KEYED ON THE AUTHENTICATED USER, NOT ON AN ADDRESS. This route answers only a
 * signed-in caller, and the resource being protected is a per-tenant read, so
 * the account is the thing to bound. It is also the honest key for the two
 * shapes this endpoint actually sees: several browser tabs on one office NAT are
 * different users and must not share a bucket, and one leaked token looping is
 * one user however many addresses it arrives from.
 */
final class MeRateLimits
{
    /**
     * A client calls this once before its first paint, and again after a change
     * that could have moved the plan or a balance. Sixty a minute is an order of
     * magnitude above a screen that re-fetched on every interaction and far
     * below anything worth calling traffic — the same figure, sized the same
     * way, as `LegalDocumentRateLimits::VIEW_PER_MINUTE` and
     * `WidgetRateLimits::FEED_PER_MINUTE`.
     *
     * ⚠️ **IT IS NOT SIZED FOR POLLING, DELIBERATELY.** 3684 refuses to publish
     * spend here precisely so that a burn rate is not computed from two polls of
     * this endpoint; a limit generous enough to make that comfortable would be
     * this file agreeing with the thing that decision declines to build.
     */
    public const int PER_MINUTE = 60;

    public static function register(): void
    {
        RateLimiter::for('me', fn (Request $request): Limit => Limit::perMinute(self::PER_MINUTE)
            ->by(self::key($request))
            ->response(fn (): Response => response()->json(
                ['message' => 'Too many requests.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            )));
    }

    /**
     * The signed-in user, or one shared bucket for anybody else.
     *
     * ⚠️ **THE FALLBACK IS NOT DEAD CODE, THOUGH IT IS UNREACHABLE TODAY.**
     * `bootstrap/app.php`'s priority list puts `AuthenticatesRequests` ahead of
     * `ThrottleRequests`, so an unauthenticated caller is answered 401 before
     * this callback ever runs. That ordering is one edit away from changing, and
     * a limiter that keyed on an optional user id alone would hand every
     * anonymous caller the *same* key — a shared bucket by accident rather than
     * by decision. This makes it a decision, and one shared bucket is stricter
     * than a free pass, which is the right direction to fail.
     */
    private static function key(Request $request): string
    {
        $user = $request->user();

        return $user instanceof User ? 'me:'.$user->id : 'me:unauthenticated';
    }
}
