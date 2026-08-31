<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Actuation\ChangeMeasurer;

/**
 * What one run of {@see ChangeMeasurer::measure()} did.
 *
 * ⛔ **NOT A VERDICT.** {@see SiteChangeVerdict} is what the *change* was judged
 * to be; this is what the *attempt* achieved, and the two must not be collapsed
 * — a deferral and an `InsufficientData` verdict are opposite facts. The first
 * says we have not answered yet and will come back; the second says we looked
 * and there is no answer to be had. Recording a deferral as a verdict would end
 * the measurement of a change nobody has measured.
 *
 * ⚠️ **IT EXISTS SO THE SWEEP CAN SAY WHAT HAPPENED.** A daily job whose only
 * output is "ran" is one nobody can tell from a job that found nothing, which is
 * the shape `sending_health_windows` taught this codebase to fear.
 */
enum SiteMeasurementOutcome: string
{
    /** Measured, recorded, and the change stays on the site. */
    case Measured = 'measured';

    /** Measured as a regression, and the page has been put back. */
    case Reverted = 'reverted';

    /**
     * Measured as a regression and the site would not take the page back.
     *
     * ⚠️ **THE EVIDENCE IS RECORDED AND THE CHANGE IS STILL LIVE**, so
     * `SiteMeasurements::dueForRevert()` picks it up again tomorrow.
     */
    case RevertFailed = 'revert_failed';

    /**
     * Search Console has not finished counting the measured window, so nothing
     * was written and the same rows will be read again tomorrow.
     */
    case Deferred = 'deferred';

    /** The window has not closed, or this change is already measured. */
    case NotDue = 'not_due';

    /** The change, or the location it belongs to, is no longer there. */
    case NotFound = 'not_found';

    /**
     * This row is the speed layer's and `28` §4.3 has already judged it.
     *
     * ⛔ **NOT `NotDue`, WHICH WOULD READ AS *"COME BACK TOMORROW"*** (5962).
     * Nothing will ever make this row due here: `App\Services\Actuation\SpeedDecider`
     * judged it on p75 LCP, INP, CLS, the conversion rate and the JavaScript
     * error rate three weeks before this sweep's window closed, and measuring it
     * again on page visits and Google clicks is a fifth trigger nobody
     * specified, arriving late, on somebody else's website.
     */
    case JudgedElsewhere = 'judged_elsewhere';

    /**
     * The change is no longer on the site, so there is nothing to judge and
     * nothing to put back.
     *
     * ⛔ **AN OWNER PRESSING UNDO IS A CUSTOMER TELLING US WE WERE WRONG**
     * (5524), and measuring it afterwards would report their decision as our
     * self-correction. `SiteMeasurements::dueForMeasurement()` has excluded these
     * rows since slice H — **what this case adds is the refusal at the service**,
     * because the sweep dispatches a job per id and the owner can press between
     * the two (5963).
     */
    case NoLongerLive = 'no_longer_live';

    /**
     * The recorded URL is not a page on this location's website any more, so
     * neither the traffic nor the revert can be attributed to it.
     *
     * ⛔ **5819(a), REFUSED RATHER THAN GUESSED** (5965). The measurement reads
     * the URL on the row and the adapter resolves that URL against whatever
     * credential the location holds **today**, so a website the owner has since
     * replaced means the page we would measure and the page we would write are
     * two different pages on two different sites. **Refusing leaves our change
     * on their site; proceeding writes a page we never touched.** Only one of
     * those is recoverable.
     */
    case PageNotIdentifiable = 'page_not_identifiable';
}
