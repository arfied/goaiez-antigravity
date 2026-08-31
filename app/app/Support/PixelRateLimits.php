<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PixelRefusal;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 3, sized for the pipeline we actually run.
 *
 * §11 asks for *"100 rps/tenant sustained, 500 burst; 30 rpm per `anonymous_id`"*.
 * ⛔ **NEITHER OF THOSE TWO KEYS IS AVAILABLE AT THE LIMITER, AND SAYING SO IS
 * BETTER THAN APPROXIMATING THEM:**
 *
 *  - **Per tenant** would need the public key, which is in the **body**. A
 *    limiter runs before the body is decoded, and decoding it in the limiter
 *    would mean doing the expensive half of the work for every request the limit
 *    exists to make cheap — §11's own *"reject early and cheaply"* inverted.
 *  - **Per `anonymous_id`** is worse: it is a value the client mints and can
 *    change per request, so a limiter keyed on it bounds only clients that
 *    volunteer to be bounded.
 *
 * So the limit here is per **source**, keyed on [[HashedIp]] — which is what
 * `PublicAuditRateLimits` established and for the reason recorded there:
 * Laravel's own limiter key is hashed but **unsalted**, and an unsalted digest of
 * a 2^32 address space is the address with an extra step. Redis or the cache
 * table holding *"which addresses posted a beacon in the last minute"* is a log
 * of visitor IPs by another name, which `29` §2's *"never store raw IP"* is
 * about.
 *
 * ⚠️ **A PER-SOURCE LIMIT IS THE WRONG SHAPE FOR THE ABUSE §11 IS DESCRIBING**,
 * and that is stated rather than dressed up: it bounds one browser, not one
 * tenant's traffic and not a distributed flood.
 *
 * ⛔ **AND THE SENTENCE THAT FOLLOWED THIS ONE PROMISED A PER-TENANT CEILING
 * THAT DID ARRIVE AND DOES NOT DO THE JOB — CORRECTED 2026-08-22 (7704).** It
 * read: *"The per-tenant ceiling arrives with the monthly cap of §11 row 4,
 * which is unbuilt because nobody has ruled on whether pixel events meter
 * against a credit pool."* [[\App\Services\Pixel\MonthlyEventCap]] is built,
 * the ruling was made — it is a **volume** cap and never touches the credit
 * ledger — and **it is not a ceiling on accepted traffic.** §11 row 4's own
 * words are *"At cap: pageviews continue"*, so a batch whose events are all
 * `pageview` is admitted past it unconditionally and for ever, and *"never
 * hard-fail"* is why that must stay true. **So this class is still the only
 * thing that refuses a beacon on volume, it is still keyed on a hashed source
 * address, and 300 a minute times fifty events a batch is 21.6 million accepted
 * events a day from one host** — times the number of hosts a distributed caller
 * has. ⚠️ **The bound that now exists is on how long that traffic costs us**
 * ([[\App\Services\Warehouse\WarehouseRetention]]) and on whether anybody
 * hears about it (`MonthlyEventCap::alertOnce()`); **a per-tenant ceiling on
 * accepted volume is still owed**, and its figure is an operator's rather than
 * this file's.
 */
final class PixelRateLimits
{
    /**
     * Beacons per minute, per source.
     *
     * ⚠️ **SIZED FROM THE BUNDLE RATHER THAN FROM A ROUND NUMBER.** `pixel.js`
     * flushes on a twenty-event queue, on page exit, and on a form submit — so an
     * ordinary visitor produces single digits per minute even while clicking
     * hard, and a shared office NAT multiplies that by the number of people
     * behind it. 300 leaves a large building room and still refuses a script.
     */
    public const int BEACONS_PER_MINUTE = 300;

    public static function register(): void
    {
        RateLimiter::for('pixel-ingest', fn (Request $request): Limit => Limit::perMinute(self::BEACONS_PER_MINUTE)
            ->by(self::key($request))
            ->response(self::refusal()));
    }

    /**
     * A per-source key that is not an address.
     *
     * The fallback matters and is the same one `PublicAuditRateLimits` uses: a
     * request with no resolvable client address gets one shared bucket rather
     * than a free pass. That makes the limit *stricter* for an unusual case,
     * which is the right direction — the alternative, a null key, is an unlimited
     * endpoint for anyone who can arrange to have no address.
     */
    private static function key(Request $request): string
    {
        return HashedIp::of($request) ?? 'pixel-ingest:unknown-origin';
    }

    /**
     * ⛔ **204, NOT 429.** §11's *"always 204"* covers this path too, and a `429`
     * would be the only response this endpoint ever gives that distinguishes one
     * outcome from another — which is exactly what the pixel cannot use and an
     * attacker can. The refusal is recorded rather than returned.
     *
     * @return callable(): Response
     */
    private static function refusal(): callable
    {
        return function (): Response {
            Log::info('pixel.ingest.refused', [
                'reason' => PixelRefusal::RateLimited->value,
                'business_id' => null,
            ]);

            return response()->noContent();
        };
    }
}
