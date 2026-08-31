<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Ops\PlatformHealth;
use App\Services\Ops\PlatformHealthChecks;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The dead man's switch — P23's "scheduler alive" half.
 *
 * ⛔ **THIS LIVES IN THE WEB PROCESS ON PURPOSE, AND MOVING IT INTO THE
 * SCHEDULER WOULD MAKE IT USELESS WHILE LEAVING EVERY TEST GREEN.** The failure
 * being watched for is the scheduler stopping, which produces **silence**: a
 * check that only runs when the scheduler runs cannot report that the scheduler
 * has not run. The web process, the scheduler and the queue workers fail
 * independently, so the one that is still up is the one that can tell you the
 * others are not.
 *
 * ⚠️ **AND IT IS HONEST ABOUT WHAT IT CANNOT SEE.** With no inbound traffic
 * nothing here runs, so a platform that is entirely down — box, web server and
 * all — pages nobody from inside itself. That is what the external HTTPS
 * monitors in the launch ops annex (T175 §3) are for, and this middleware is not
 * a substitute for them. It covers the far commoner shape: requests arriving
 * normally while nothing behind them is running.
 *
 * ⛔ **THAT PARAGRAPH UNDERSTATED ITS OWN GAP BY A WHOLE DIMENSION UNTIL
 * 2026-08-26, AND THE UNDERSTATEMENT IS THE PART WORTH KEEPING (9930–9944).**
 * It reads as *"some traffic is enough"*, and the real condition was **some
 * traffic within about twenty-four hours of the process stopping**:
 * {@see PlatformHealth::lastBeat()} bounded its read to a day,
 * so a request arriving on day two found a `null` last beat — the same value as
 * *never beaten* — and the sweep skipped it in silence, for ever. **A quiet
 * weekend was enough to lose the alert permanently**, after which this platform
 * had no instrument for a dead queue worker of any kind. ⚠️ **The horizon is
 * gone and the sentence above is true as written now**: one request at any later
 * moment rings it. What is left is the honest limitation that paragraph always
 * claimed to be describing, rather than a much narrower one wearing its words.
 *
 * ## What it costs, and why that is affordable
 *
 * ⚠️ **IT RUNS AFTER THE RESPONSE HAS BEEN SENT**, in `terminate()`, so nothing
 * here is on anybody's page-load path — and it is throttled to one sweep a
 * minute by a cache key, so the cost per request is one `Cache::add()`.
 *
 * ⚠️ **THAT IS A WRITE RATHER THAN A READ ON THIS PLATFORM, AND SAYING "A CACHE
 * READ" WOULD BE THE COMFORTABLE VERSION.** Production runs the database cache
 * driver, so `add()` is an insert attempt per request. It is affordable because
 * the session store is on the same driver and already writes once per request —
 * this roughly doubles a cost that exists rather than introducing a new class of
 * one. **If that ever stops being true, the throttle moves, not the check**: the
 * check has to be here, in the process that survives the scheduler.
 *
 * ⚠️ **THE THROTTLE FAILING OPEN IS THE SAFE DIRECTION AND IT IS DELIBERATE.**
 * A cache that is unavailable makes `Cache::add()` throw, which this catches and
 * treats as "do not sweep" — the sweep is skipped, no alert is missed for longer
 * than the next request, and a broken cache does not turn every inbound webhook
 * into a heartbeat query.
 *
 * ⛔ **NOTHING IT DOES MAY AFFECT THE REQUEST** (R25). It runs after the
 * response, it catches everything, and a failure inside it is a log line.
 */
final class WatchPlatformHeartbeats
{
    /**
     * One sweep a minute across the whole fleet.
     *
     * ⚠️ **A MINUTE RATHER THAN THE STALENESS THRESHOLD.** The threshold is how
     * old a beat may be before it is an alert; this is how often anybody looks.
     * Tying them together would mean a fifteen-minute threshold is only noticed
     * every fifteen minutes, so the actual time-to-page would be up to twice
     * what an operator configured.
     */
    public const int THROTTLE_SECONDS = 60;

    public const string THROTTLE_KEY = 'ops:heartbeat-watch';

    public function __construct(private readonly PlatformHealthChecks $checks) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            // ⚠️ `add()` rather than `has()` + `put()`: two web workers finishing
            // in the same millisecond would both pass the second form, and the
            // alert path would run twice. `add()` is atomic on every store this
            // application uses.
            if (! Cache::add(self::THROTTLE_KEY, true, self::THROTTLE_SECONDS)) {
                return;
            }

            $this->checks->sweepHeartbeats();
        } catch (Throwable $e) {
            Log::warning('the platform heartbeat watch could not run', [
                'exception' => $e::class,
            ]);
        }
    }
}
