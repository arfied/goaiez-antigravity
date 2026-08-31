<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Visibility\VisibilityReading;

/**
 * The six things this application can truthfully say about a location's
 * Google Search visibility.
 *
 * `28` §5.3.4 tabulates four degradation rows and decision 1081 records that
 * **this application can occupy none of them**: three require the pixel, which
 * does not exist, and the fourth requires GBP, which is unapproved. The reachable
 * state is GSC alone, which that table never contemplated. These are that
 * state's honest decomposition, and the split is the whole point of the type —
 * `28` §5.3.4's own wording is *"never imply the number is zero"*, and a `?int`
 * puts *we asked and there were none*, *we cannot ask*, and *we have not asked
 * yet* into one absence (decision 1084).
 *
 * The order below is the order an owner travels through them, and the first four
 * are all "nothing to show" for four different reasons with four different
 * remedies — connect an account, pick a property, wait for us, wait for Google.
 *
 * ⚠️ **THERE ARE SIX, AND THERE WERE FIVE UNTIL 2026-08-26.**
 * {@see self::NotReadYet} closes the gap between *"we have not asked yet"* and
 * *"we asked and Google had nothing"*, which this docblock's own third
 * distinction named and no case carried (9820–9839).
 */
enum VisibilityState: string
{
    /**
     * No Search Console connection exists for this tenant.
     *
     * The remedy is the owner's: authorise Google. Nothing here is broken.
     */
    case NotConnected = 'not_connected';

    /**
     * Connected, but nobody has said which site property this location is.
     *
     * Decision 1083 refuses to infer it from the website URL — one Google account
     * commonly holds many properties, and a business whose site is a page on a
     * franchisor's domain would be silently mapped to the franchisor's whole
     * property.
     */
    case NoPropertyChosen = 'no_property_chosen';

    /**
     * Connected, a property is chosen, and **this platform has not read the
     * window from Google** — added 2026-08-26 (9820–9839).
     *
     * ⛔ **THIS TYPE'S OWN FOUNDING DOCBLOCK NAMED THREE DISTINCTIONS AND THE
     * TYPE IMPLEMENTED TWO OF THEM.** Decision 1084's sentence, repeated at the
     * head of {@see VisibilityReading} and again in
     * `VisibilityReadingTest`, is that `?int` cannot distinguish *we asked and
     * there were none* from *we cannot ask* from ***we have not asked yet***.
     * The first is {@see self::NoDataYet}, the second is {@see self::Unavailable}
     * and {@see self::NotConnected}/{@see self::NoPropertyChosen} — and **the
     * third had no case**, so it arrived as `NoDataYet` and the screen said
     * *"Google Search has nothing to report for this period yet"* about a
     * location whose nightly sync had never run.
     *
     * ⛔ **A STATE RATHER THAN A REASON CODE UNDER `Unavailable`, AND THE
     * ARGUMENT IS ENFORCEMENT.** *"We have not asked"* is not *"we could not
     * ask"*: `Unavailable` promises the source refused, and every caller with a
     * `match` on this enum renders that promise. A caller that reads the state
     * and not the reason — which both callers in this tree did until 9820 — says
     * *"Search numbers are temporarily unavailable"* about a location we simply
     * have not got to. **A state is enforced by the compiler; a reason is
     * enforced by whoever remembers.**
     *
     * ⚠️ **EVERY MEMBER OF ITS POPULATION IS OURS**, and
     * {@see VisibilityAbsenceReason} says which: never read, the
     * last read failed, reading is stopped, or this window is from before our
     * first read of this location.
     */
    case NotReadYet = 'not_read_yet';

    /**
     * Connected, a property is chosen, we have read this window, and Google
     * returned no rows for it.
     *
     * ⚠️ **This is NOT zero, and since 2026-08-26 it is not "we have not
     * looked" either** — {@see self::NotReadYet} carries that, and reaching this
     * case now requires a recorded read that covered the window.** `28` §5.3.5 names it as an honest gap in its own
     * right — *"The catchment map needs traffic. A new business sees nothing for
     * weeks"* — and §5.3.4's last row says *"Never render an empty map as though
     * it were a finding"*. A brand-new property with no impressions and a mature
     * property that genuinely got none look identical from here and neither is a
     * measurement.
     */
    case NoDataYet = 'no_data_yet';

    /**
     * We asked, Google answered, and there are numbers.
     */
    case Measured = 'measured';

    /**
     * The source could not be asked, and this is the state the whole type exists
     * for.
     *
     * Revoked grant, a permission this account does not hold on that property,
     * a quota wall, or Google being down. Decision 229's finding applied to a
     * second surface: *"a check cannot run" has six causes and two of them
     * invert* — calling Cloudflare *"your website is down"* is a false
     * accusation, and rendering an unaskable source as `0` impressions is the
     * same false accusation aimed at the owner's own business.
     */
    case Unavailable = 'unavailable';
}
