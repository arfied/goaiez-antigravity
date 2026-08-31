<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Ops\OperatorAlerts;
use Illuminate\Support\Facades\Route;

/**
 * Who caused a bell to be rung — this platform, or somebody outside it
 * (7820–7839).
 *
 * ## ⛔ The defect this exists to answer
 *
 * ⛔ **A PERSON WHO KNOWS A TENANT'S PUBLIC PIXEL KEY COULD MAKE THIS PLATFORM
 * SEND A TEXT MESSAGE AND AN EMAIL, INSIDE THEIR OWN HTTP REQUEST.**
 * `PixelCollector`'s refusal path calls `IngestRejects::record()` and its
 * admitted path calls `MonthlyEventCap::alertOnce()`; both reach
 * {@see OperatorAlerts::raise()}, whose only rate limit is a de-duplication
 * keyed on `(kind, subject)` — **and the subject on both is a business id.**
 * So the bound was *one page per tenant per quiet window*, the quiet window is
 * an operator's to set as low as a minute, the keys are public by construction
 * (they sit in the page source of every tenant's own website), and
 * `PlatformTexter::alertOperator()` deliberately ignores `sms.enabled` and
 * `messaging.global_halt` so that halting the platform does not slow it.
 *
 * ⛔ **THE SHARPEST WAY TO SAY IT IS NOT COST, IT IS THAT THE PAGER WAS THE
 * TARGET.** A flood of tenant-scoped bells is a handset nobody can read and a
 * daily mail ceiling — `MailQuota`, which counts platform mail too — spent on
 * somebody else's traffic. **The only remedy available to a paged operator was
 * to blank `ops.alert_sms`, which is to say: turn the pager off.**
 *
 * ⚠️ **AND THE BOUND THIS ENUM MAKES POSSIBLE IS ITSELF ANNOUNCED SINCE
 * 2026-08-22** (7839(a), closed at 8120–8139). Withholding a push leaves the
 * handset quiet, and **quiet is also what it goes when somebody fixes the
 * thing**; {@see OperatorAlertKind::PagerBudgetSpent} is the one message that
 * tells those two apart, and it is raised with the **provoking alert's own
 * origin** rather than from its surroundings — see
 * {@see OperatorAlerts::ringFlood()} for why ambient detection is wrong there
 * even though it happens to agree in production.
 *
 * ## Why the answer is a fact about the caller rather than about the kind
 *
 * ⚠️ **THE KIND CANNOT ANSWER IT AND MUST NOT BE ASKED TO.**
 * {@see OperatorAlertKind} is a vocabulary of *what broke*; nothing in
 * `PixelIngestRejects` says whether our own clock noticed it or a stranger
 * provoked it, and a second table mapping kinds to origins would be a list that
 * goes stale in the file that cannot see the call sites — `CLAUDE.md`'s first
 * recurring shape, aimed at a bell.
 *
 * ⚠️ **A ROUTE IS THE HONEST TEST, AND IT IS THE ONE THING TRUE OF EVERY PATH A
 * STRANGER CAN REACH.** `Route::current()` is non-null exactly while an HTTP
 * request is being dispatched, and null in `artisan`, in a scheduled command and
 * in a queue worker — the three places this platform's own machinery raises
 * from. ⛔ **`App::runningInConsole()` was refused and the reason is worth
 * writing down**: it is **true inside every test in this suite**, including the
 * ones that post to a route, so a control built on it would classify the very
 * request the finding is about as *"our own clock"* and could never be driven
 * red. A mitigation whose test cannot fail is 256's shape at its most expensive.
 *
 * ## ⛔ The one exception, and it is structural rather than a list
 *
 * ⛔ **`PlatformHealthChecks` RAISES FROM AN ORDINARY WEB REQUEST ON PURPOSE**,
 * and it is the raiser that matters most: `WatchPlatformHeartbeats::terminate()`
 * drives `sweepHeartbeats()` after the response, because *"a check that only
 * runs when the scheduler runs cannot report that the scheduler has not run."*
 * A route is current there, so ambient detection would call the dead-scheduler
 * bell stranger-provoked — **the remedy silencing the one alert it exists to
 * protect.** That class therefore states {@see self::Platform} at every raise,
 * in its own file, beside the sentence explaining why it is on the web path at
 * all.
 *
 * ⚠️ **AMBIENT DETECTION IS A DEFAULT AND NEVER A CONCLUSION** (398). A raiser
 * that knows better says so; a raiser that says nothing gets the reading its
 * surroundings support, and the only cost of the reading being *wrong* in the
 * unsafe direction is a bounded, recorded, still-logged, still-listed alert —
 * never a swallowed one.
 */
enum AlertOrigin: string
{
    /**
     * This platform's own clock or process — a scheduled command, a queue
     * worker, a sweep, or a check the web process runs on the platform's behalf.
     *
     * ⛔ **NOTHING BOUNDS A PLATFORM-ORIGIN PUSH AND NOTHING MAY LEARN TO.**
     * Every alert that pages about this platform being broken is one of these,
     * and {@see OperatorAlerts::raise()} delivers it inline, on both channels,
     * exactly as it did before any of this existed.
     */
    case Platform = 'platform';

    /**
     * An inbound HTTP request asked for something and a bell rang on the way.
     *
     * ⚠️ **NOT ALL OF THESE ARE UNAUTHENTICATED AND THE DISTINCTION IS
     * DELIBERATELY NOT DRAWN.** The pixel collector is open to anybody; the
     * Zernio account webhook and the voice webhook are signed but are still
     * somebody else deciding when we do work. **The property that matters is
     * that the timing is not ours**, and a vendor retrying a webhook in a loop
     * produces the same handset as a stranger with a script.
     */
    case Request = 'request';

    /**
     * What the surroundings say, for a raiser that has not stated its own.
     */
    public static function detected(): self
    {
        return Route::current() === null ? self::Platform : self::Request;
    }
}
