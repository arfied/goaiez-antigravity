<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\OutboundSiteRefusal;
use App\Enums\WordPressConnectionRefusal;
use App\Services\Actuation\WordPress\WordPressRestClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * How hard this platform is allowed to push a customer's own web server.
 *
 * ⛔ **IT IS A CAP AND NOT A COUNTER, AND 4687 IS WHY THE DIFFERENCE IS STATED
 * RATHER THAN LEFT TO BE INFERRED.** {@see self::reserve()} returns a refusal
 * and {@see WordPressRestClient::attempt()} throws
 * rather than sending — so an exhausted budget refuses a request. A class that
 * merely counted would be `sending_health_windows` with somebody else's server
 * attached: a number nobody reads, on the one path in `app/` that touches
 * hardware we do not own.
 *
 * ⛔ **THE GAP IT FILLS IS 6055's.** `WordPressRestClient` was taken out of
 * `FetchGateway` deliberately and correctly — robots has nothing to say about an
 * authorised write to your own CMS — but the gateway is also where the
 * politeness budget lived, and nothing replaced it. `ActuationRateLimits` is not
 * that budget and never was: it registers the two **inbound** T3 route limiters
 * and touches outbound writes not at all.
 *
 * ## Why the key is the host and not the tenant
 *
 * ⚠️ **THE THING BEING PROTECTED IS A MACHINE, AND A MACHINE HAS A HOSTNAME.**
 * Two locations of one tenant on one WordPress install are one server and share
 * one budget, which is the answer that is right about the server. Two *different*
 * tenants on one host — an agency multisite, a shared reseller domain — also
 * share it, and that is a denial vector one tenant can point at another. It is
 * accepted rather than overlooked: they are the same server, the impoliteness is
 * the same impoliteness, and a per-tenant budget on a shared host would let
 * `n` tenants multiply the pressure by `n` while every one of them read as
 * compliant.
 *
 * ⚠️ **THE HOST IS HASHED INTO THE KEY.** `PixelRateLimits`' argument, one step
 * along: a limiter bucket is a shared keyspace that outlives the request, and a
 * tenant's own domain sitting in it in clear is a list of our customers
 * available to anything that can run `KEYS`. The hash costs nothing and an
 * operator who needs to look up one host can compute it.
 *
 * ## The numbers, and where they come from
 *
 * ⚠️ **SIZED FROM WHAT ONE OPERATION COSTS, NEVER FROM A ROUND NUMBER** —
 * `ActuationRateLimits`' rule. Counted against this tree on 2026-08-20, one
 * growth-page publish that creates a page makes **eight** requests to the
 * customer's host through this client: two for `WordPressAdapter::health()`
 * (`identify` + `canManagePlugins`), two for the snapshot's `locate()` (pages
 * then posts), two more for the write-time gate `siteForWriting()` (5985), the
 * `POST` itself, and the read-back 5980 requires. A revert is three to six. So
 * {@see self::REQUESTS_PER_MINUTE} is a little over seven publishes a minute
 * against one host, and {@see self::REQUESTS_PER_DAY} is seventy-five —
 * comfortably above `PublishingVolume`'s ceiling of four pages and four posts
 * per location per month, and far below anything a small-business host would
 * notice.
 *
 * ⛔ **A REFUSAL CAN LAND IN THE MIDDLE OF AN OPERATION, AND THAT IS SAID HERE
 * RATHER THAN ASSUMED AWAY** (314–316). If the budget runs out between the
 * `POST` and the read-back, the write landed and this platform records a
 * failure. **It is not a new failure mode** — a site that times out in the same
 * gap does exactly the same thing, and `assertTook()`'s abort path and
 * `SiteMeasurements::dueForRevert()`'s retry are what already exist for it — but
 * it is a new *cause* of one, and it is the reason the per-minute figure is
 * seven operations rather than one.
 *
 * ## The cooldown is the other half, and it is what makes `Retry-After` mean
 * something
 *
 * ⛔ **A `429` WHOSE `Retry-After` IS DISCARDED IS A `429` IGNORED.** Before
 * this class, `classified()` read the status and nothing else. Now every
 * response passes {@see self::noteResponse()}, and a host that asks for room
 * gets it — for the time it named, in either wire form {@see RetryAfter} reads.
 */
final class OutboundSiteBudget
{
    /**
     * Requests to one host in a rolling minute.
     */
    public const int REQUESTS_PER_MINUTE = 60;

    /**
     * Requests to one host in a rolling day.
     */
    public const int REQUESTS_PER_DAY = 600;

    /**
     * How long a `429` with nothing readable in its `Retry-After` buys the host.
     *
     * ⛔ **A `429` ALWAYS COOLS DOWN, HEADER OR NO HEADER.** RFC 6585 §4 makes
     * the header a `MAY`, so *"too many requests"* with no number is a
     * conforming answer and is the commonest real one. Treating the absent
     * header as *"carry on"* would be reading a refusal as permission.
     */
    public const int DEFAULT_COOLDOWN_SECONDS = 60;

    /**
     * The longest a host's own answer may stop us.
     *
     * ⚠️ **A CEILING ON SOMEBODY ELSE'S NUMBER, BECAUSE IT IS SOMEBODY ELSE'S
     * NUMBER.** A misconfigured proxy answering `Retry-After: 31536000` would
     * otherwise take a customer's website out of this platform for a year, and
     * nothing would say so. A day is long enough that a real maintenance window
     * is honoured and short enough that a wrong one costs one night.
     */
    public const int MAX_COOLDOWN_SECONDS = 86_400;

    /**
     * The shortest cooldown that is stored at all.
     *
     * ⚠️ **A HOST THAT NAMES AN INSTANT ALREADY PAST GETS ONE SECOND, NOT
     * ZERO.** {@see RetryAfter} answers `0` for exactly that, and a zero-second
     * cache entry is a cache entry that is not there — so the floor is what
     * keeps *"we were told to wait"* distinguishable from *"we were told
     * nothing"* in the store. The per-minute cap is what bounds the retry rate
     * afterwards; this floor is not doing that job and must not be read as
     * though it were.
     */
    public const int MIN_COOLDOWN_SECONDS = 1;

    /**
     * The bucket a request with no readable host falls into.
     *
     * `PublicAuditRateLimits`' fallback, and the same reasoning: one shared
     * bucket makes the limit **stricter** for an unusual case rather than absent
     * for it. A URL this platform built with no host in it is a defect, and a
     * defect that hammers one bucket is visible where a defect with a free pass
     * is not.
     */
    private const string UNKNOWN_HOST = 'unknown-host';

    /**
     * The host an outbound URL names, lowercased.
     *
     * ⚠️ **CASE-FOLDED, BECAUSE DNS IS AND THE STRING IS NOT.**
     * `https://Example.com/` and `https://example.com/` are one server and must
     * be one bucket; the URL is built from `locations.website_url`, which an
     * owner typed.
     */
    public static function hostOf(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? mb_strtolower($host) : self::UNKNOWN_HOST;
    }

    /**
     * Take one request's worth of budget for this host, or say whose brake
     * stopped it.
     *
     * ⛔ **`null` IS PERMISSION AND AN {@see OutboundSiteRefusal} IS A REFUSAL,
     * AND THIS RETURNED A BARE `bool` UNTIL 9800–9819.** A `false` could say
     * *that* we did not send and never *why*, so both causes below arrived at
     * {@see WordPressConnectionRefusal::Busy} and an owner was told their
     * website had asked us to slow down when their website had said nothing.
     * **The two are not a detail of the same fact**: one is a customer's server
     * giving this platform an instruction, the other is this platform's own cap
     * on its own pressure.
     *
     * ⚠️ **THE RENAME FROM `consume()` IS PART OF THE CHANGE AND NOT TIDYING.**
     * `if (! consume($host))` went on compiling under the new return type and
     * meant its own opposite — `! null` is `true` — so the old name is gone and
     * a stale call site is a fatal rather than a gate that fires backwards.
     *
     * ⛔ **RESERVED BEFORE THE REQUEST, NEVER COUNTED AFTER IT.** A counter
     * incremented on the way back does not count the request that is in flight,
     * so `n` workers starting at once all see `n-1` and all proceed — which is
     * precisely the burst a politeness budget exists to stop.
     *
     * ⚠️ **THE COOLDOWN IS CHECKED FIRST AND IT IS NOT A THIRD BUCKET.** It is
     * the host's own instruction, and it outranks our own arithmetic: a host
     * inside its cooldown is refused with budget to spare.
     *
     * ⚠️ **THE MINUTE IS STILL ASKED BEFORE THE DAY**, which the `||` this
     * replaced also did by short-circuit. The order is now load-bearing rather
     * than incidental: it decides which of the two sentences an owner reads, and
     * the per-minute window is the one that clears while they are still looking
     * at the form.
     */
    public static function reserve(string $host): ?OutboundSiteRefusal
    {
        if (self::coolingDownFor($host) > 0) {
            return OutboundSiteRefusal::HostAskedForRoom;
        }

        $minute = self::key($host, 'minute');
        $day = self::key($host, 'day');

        if (RateLimiter::tooManyAttempts($minute, self::REQUESTS_PER_MINUTE)) {
            return OutboundSiteRefusal::MinuteBudgetSpent;
        }

        if (RateLimiter::tooManyAttempts($day, self::REQUESTS_PER_DAY)) {
            return OutboundSiteRefusal::DayBudgetSpent;
        }

        RateLimiter::hit($minute, 60);
        RateLimiter::hit($day, 86_400);

        return null;
    }

    /**
     * Read the host's own instruction out of a response and act on it.
     *
     * ⛔ **`429` UNCONDITIONALLY, `5xx` ONLY WHEN IT ASKED.** A `429` is the host
     * saying we are asking too much, and RFC 6585 makes the number optional, so
     * the absence of a number is not the absence of the instruction. A `5xx` is
     * the host being unwell, which is a different sentence: backing off for a
     * minute on every `500` would turn one broken plugin into a platform-wide
     * pause on that customer, so a `5xx` moves this only when it carries a
     * `Retry-After` of its own — which RFC 9110 §10.2.3 documents for `503`.
     *
     * ⚠️ **A `3xx` IS DELIBERATELY NOT HONOURED, THOUGH §10.2.3 DEFINES THE
     * FIELD FOR IT.** This client does not follow redirects (a `301` off an
     * authenticated request hands a working Basic Auth header to whatever the
     * redirect names), so a `3xx` here is a permanent configuration fact about
     * the address rather than a temporary one about the server, and a cooldown
     * would only delay the same wrong answer.
     */
    public static function noteResponse(string $host, Response $response, ?CarbonImmutable $now = null): void
    {
        $status = $response->status();

        if ($status !== 429 && ! $response->serverError()) {
            return;
        }

        $asked = RetryAfter::seconds($response->header('Retry-After'), $now);

        if ($asked === null && $status !== 429) {
            return;
        }

        self::backOff($host, $asked ?? self::DEFAULT_COOLDOWN_SECONDS, $now);
    }

    /**
     * Seconds left on this host's cooldown, or `0` when it is not on one.
     */
    public static function coolingDownFor(string $host, ?CarbonImmutable $now = null): int
    {
        $until = Cache::get(self::key($host, 'cooldown'));

        if (! is_int($until)) {
            return 0;
        }

        return max(0, $until - ($now ?? CarbonImmutable::now())->getTimestamp());
    }

    /**
     * Stop sending to this host for a while.
     *
     * ⚠️ **THE LONGER OF THE TWO WINS.** A second `429` naming a shorter wait
     * than one already in force is not permission to start again sooner; the
     * host's strictest recent instruction is the one that stands.
     */
    private static function backOff(string $host, int $seconds, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();

        $seconds = max(self::MIN_COOLDOWN_SECONDS, min($seconds, self::MAX_COOLDOWN_SECONDS));
        $seconds = max($seconds, self::coolingDownFor($host, $now));

        Cache::put(
            self::key($host, 'cooldown'),
            $now->getTimestamp() + $seconds,
            $seconds,
        );
    }

    private static function key(string $host, string $window): string
    {
        return 'outbound-site:'.$window.':'.hash('sha256', $host);
    }
}
