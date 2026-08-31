<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a reading of a real-user measurement actually is — three answers, never
 * two.
 *
 * ⛔ **"NOT ENOUGH DATA" IS A VERDICT OF ITS OWN AND MAY NEVER COLLAPSE INTO A
 * NUMBER** (decision 229, restated for the actuation chain at `BUILD-PLAN.md`
 * §2.11.2). A thin sample reported as a p75 manufactures evidence, and decision
 * 4861 makes that the *expected* case rather than an edge one: almost no
 * production traffic has reached the pixel, so for most tenants and for some
 * time [[self::InsufficientData]] is the honest answer to nearly every
 * question asked here.
 *
 * ⛔ **AND A SIGNAL THAT CANNOT EXIST IS NOT A MEASURED ZERO** (`BUILD-PLAN.md`
 * §2.11.5 conflict 6). A tenant whose website has never sent a measurement has
 * no speed reading at all; a tenant who sends measurements but few of this kind
 * has a reading that is too thin to state. Those are different facts about the
 * business and they lead to different actions — the first is an installation
 * problem and the second is a patience problem — so they are different cases.
 */
enum VitalSampleState: string
{
    /**
     * Enough samples to state a percentile.
     */
    case Measured = 'measured';

    /**
     * Measurements are arriving from this website, but too few of this metric,
     * on this device class, in this window.
     *
     * ⚠️ **INCLUDES ZERO SAMPLES.** A tenant whose desktop visitors are all
     * measured and whose tablet visitors are none is not un-instrumented; they
     * have no tablet visitors. Reporting that as "no measurements" would send
     * somebody to check a script that is working perfectly.
     */
    case InsufficientData = 'insufficient_data';

    /**
     * Nothing has ever been measured for this business.
     *
     * ⚠️ **IT CONFLATES TWO CAUSES AND SAYS SO RATHER THAN GUESSING BETWEEN
     * THEM**: a website with no pixel on it, and a website with a pixel that
     * nobody has visited. Separating them needs a *pixel sighting* — the fact
     * `BUILD-PLAN.md` §2.11.2 has `ActuationTiers` deriving T3 from, which is
     * slice B's and does not exist yet. A pixel **key** cannot stand in for it:
     * `TenantProvisioner` mints one for every tenant at registration, so its
     * existence says only that somebody signed up.
     */
    case NoMeasurements = 'no_measurements';
}
