<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Actuation\SpeedDecider;

/**
 * `28` §4.3's four auto-rollback triggers, one case each, transcribed:
 *
 * *"Auto-rollback triggers (any one): p75 LCP or INP worsens >10% over 7 days vs
 * the 14-day pre-change baseline · CLS worsens at all · form-submit or booking
 * conversion rate drops >15% with ≥100 sessions · JS error rate on affected
 * pages doubles."*
 *
 * ⛔ **FOUR CASES BECAUSE THERE ARE FOUR TRIGGERS, NOT FIVE.** LCP and INP share
 * one — the document says *"LCP **or** INP"* with one threshold — and splitting
 * them would make a test named *"each trigger driven alone"* mean something
 * different from what §4.3 says. Which metric moved is carried in the evidence
 * and in the sentence, not in the vocabulary.
 *
 * ⚠️ **EVERY ONE OF THEM IS UNFALSIFIABLE WITHOUT ITS SAMPLE FLOOR**, and each
 * floor is somebody else's constant rather than one invented here — see
 * {@see SpeedDecider}. A trigger evaluated over a signal that cannot exist is
 * 256's vacuous pass wearing a threshold (`BUILD-PLAN` §2.11.5 conflict 6).
 */
enum SpeedTrigger: string
{
    /**
     * p75 LCP or INP worsened by more than `28` §4.3's threshold.
     *
     * ⚠️ **THE THRESHOLD IS `SiteVitals::MATERIAL_CHANGE_BASIS_POINTS` AND IS
     * NOT RE-TYPED HERE** (5617's explicit instruction). It is the same 10% the
     * speed statement already uses, and two copies of it would drift on the day
     * one of them was tuned.
     */
    case LoadingOrResponsivenessWorsened = 'loading_or_responsiveness_worsened';

    /**
     * Layout shift worsened at all.
     *
     * ⛔ **"AT ALL" IS VERBATIM AND THE SAMPLE FLOOR IS THE WHOLE OF THE
     * PROTECTION** (5858). There is no threshold to hide behind here, so a
     * single bucket of movement on a hundred-visit fortnight is a regression by
     * §4.3's own wording — which is only defensible because a window below
     * `SiteVitals::MINIMUM_SAMPLES` never reaches the comparison at all. 3789's
     * lesson, transplanted: one wobble on a tiny sample may not contain
     * anybody.
     */
    case LayoutShiftWorsened = 'layout_shift_worsened';

    /**
     * The conversion rate fell by more than §4.3's threshold, over enough
     * sessions for the fall to mean something.
     *
     * ⛔ **IT ARMS ONLY WHERE CONVERSIONS ARE TRACKED, AND THAT FALLS OUT OF THE
     * ARITHMETIC RATHER THAN BEING BOLTED ON** (§2.11.5 conflict 6). A baseline
     * window with no conversions in it has no rate to fall from, so the trigger
     * cannot fire — the same shape `SiteVitals::compare()` gives the zero
     * baseline one class away, and the reason a tenant with no conversion
     * tracking is never reverted on a signal they do not have.
     */
    case ConversionRateFell = 'conversion_rate_fell';

    /**
     * The JavaScript error rate doubled.
     *
     * ⚠️ **A ZERO BASELINE IS A REGRESSION THE MOMENT ANY ERRORS APPEAR, AND
     * THAT IS THE OPPOSITE DECISION FROM THE CONVERSION ARM** (5859). Nothing
     * doubles from zero, so the two arms had to be ruled on separately. Errors
     * going from none to some is the exact failure §4.2 calls *"the only risky
     * fix"* — a deferred script that broke — and this codebase already answers
     * the zero baseline this way one class away: *"zero-to-zero is `Unchanged`
     * and zero-to-anything is `Worsened`"* (`SiteVitals::compare()`). The
     * conversion arm is the mirror image because a conversion rate falling from
     * zero is not a fall.
     */
    case JsErrorRateDoubled = 'js_error_rate_doubled';
}
