<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Actuation\T3Payload;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two public T3 injection routes' limits — `PixelRateLimits`' argument, with
 * one difference that matters.
 *
 * ⚠️ **PER SOURCE, KEYED ON {@see HashedIp}**, for the reason recorded there:
 * Laravel's own limiter key is hashed but **unsalted**, and an unsalted digest
 * of a 2^32 address space is the address with an extra step. The bucket store is
 * otherwise a log of visitor IPs by another name, which `29` §2's *"never store
 * raw IP"* is about.
 *
 * ⚠️ **THE DIFFERENCE FROM THE COLLECTOR: THE KEY IS IN THE URL HERE.** So a
 * per-tenant limit *is* available at the limiter, unlike at ingest where the key
 * is in the body. It is deliberately not used — a per-tenant bucket on a public
 * read is a way for one visitor to exhaust a whole tenant's allowance and take
 * that tenant's injected content off every page of their website until the
 * window rolls. Per source bounds the abuser and leaves everybody else served.
 *
 * ⛔ **NEITHER LIMIT ANSWERS 429.** `PixelRateLimits`' rule: on a stranger's
 * website the visible consequence of any refusal must be that nothing happens.
 * The payload limit answers the **empty payload**, which is what every other
 * refusal on that route answers, so a rate-limited caller cannot tell itself
 * apart from an unknown key.
 */
final class ActuationRateLimits
{
    /**
     * Payload reads per minute, per source.
     *
     * ⚠️ **SIZED FROM THE CLIENT RATHER THAN FROM A ROUND NUMBER**: the module
     * fetches once per page load and the response is cacheable for a minute, so
     * an ordinary visitor produces one request per minute at most. 120 leaves a
     * shared office NAT enormous room and still refuses a script.
     */
    public const int PAYLOAD_PER_MINUTE = 120;

    /**
     * Module fetches per minute, per source. Lower, because it is cached for
     * five times as long and is one request per browser per five minutes.
     */
    public const int MODULE_PER_MINUTE = 60;

    public static function register(): void
    {
        RateLimiter::for('t3-payload', fn (Request $request): Limit => Limit::perMinute(self::PAYLOAD_PER_MINUTE)
            ->by(self::key($request, 't3-payload'))
            ->response(static fn (): Response => response((new T3Payload([]))->json(), 200, [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => '*',
            ])));

        RateLimiter::for('t3-module', fn (Request $request): Limit => Limit::perMinute(self::MODULE_PER_MINUTE)
            ->by(self::key($request, 't3-module'))
            ->response(static fn (): Response => response('', 200, [
                'Content-Type' => 'application/javascript; charset=UTF-8',
            ])));
    }

    /**
     * A per-source key that is not an address.
     *
     * The fallback is `PublicAuditRateLimits`': a request with no resolvable
     * client address gets one shared bucket rather than a free pass, which makes
     * the limit stricter for an unusual case rather than absent for it.
     */
    private static function key(Request $request, string $bucket): string
    {
        return $bucket.':'.(HashedIp::of($request) ?? 'unknown-origin');
    }
}
