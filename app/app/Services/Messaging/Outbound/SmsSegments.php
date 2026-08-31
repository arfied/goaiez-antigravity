<?php

declare(strict_types=1);

namespace App\Services\Messaging\Outbound;

/**
 * Roughly how many carrier segments a body will be billed as — the **one**
 * estimator this application has (decision 4800).
 *
 * ⛔ **IT IS A CLASS BECAUSE 2976 REFUSED THE SECOND COPY, NOT BECAUSE A
 * PRIVATE METHOD WAS UNTIDY.** This lived as `PlatformMessageSender::segments()`
 * and was the stated blocker on booking the review invite's own SMS: *"pricing
 * it needs the segment estimate that lives as a private method over there, and a
 * second copy of a cost estimator is two figures that drift in the one book
 * whose purpose is reconciliation."* That objection is answered by **extraction
 * and only by extraction** — copying the six lines into `ReviewInviteSender`
 * would have closed the gap and created exactly the drift 2976 named, on the one
 * book whose job is margin.
 *
 * ⚠️ **SO THE INVARIANT IS "EXACTLY ONE", AND IT IS LINTED RATHER THAN
 * REMEMBERED** (4801). A `MessagingTest` assertion fails the build if the
 * `153` / `160` segment arithmetic appears anywhere in `app/` outside this file,
 * because the failure mode of a second copy is silent: both books look
 * plausible and only their difference is wrong, and nothing reconciles them.
 *
 * ## What it does not model, carried across unchanged
 *
 * ⚠️ **AN ESTIMATE, AND UNLIKE THE SMS LOG DRIVER'S IT DOES REACH A NUMBER
 * SOMEBODY CARES ABOUT** — so the limits are worth naming. Real GSM-03.38
 * segmentation depends on the alphabet, on seven characters that cost two units
 * each, and on a UCS-2 fallback the moment one character falls outside the basic
 * set. None of that is modelled, so a message with an emoji in it is
 * under-counted and the internal book reads slightly cheap.
 *
 * ⛔ **IT IS NOT ALLOWED TO REACH THE RETAIL BOOK AND IT CANNOT** — the retail
 * debit does not turn on the segment count at all, and neither caller's credit
 * debit ever sees it. ⚠️ **THIS SAID "R9 CHARGES ONE CREDIT PER SEND WHATEVER
 * THIS RETURNS" AND 9182 MOVED THE FIGURE WITHOUT MOVING THE PROPERTY**: retail
 * is one credit for the text and one for the media, and a three-segment text is
 * still one credit while a one-segment text with a picture on it is two. **What
 * this returns changes neither.** The authoritative segment count arrives later, on the delivery
 * receipt, as Infobip's own `messageCount` (2549) — which is the figure a
 * reconciliation should eventually trust over this one.
 *
 * ⚠️ **`PlatformTexter` AND THE SMS DRIVERS ARE NAMED IN PROSE RATHER THAN WITH
 * A `{@see}`, AND THE OMISSION IS LOAD-BEARING.** Pint's
 * `fully_qualified_strict_types` promotes a `{@see}` into a real `use`
 * statement, and the messaging lint holds `App\Contracts\Texter` and its two
 * drivers to five files so nothing can reach a carrier without the permit gate.
 * This class counts characters and must never become the sixth.
 */
final class SmsSegments
{
    /**
     * How many segments this body is estimated to be billed as.
     *
     * Never below 1: a sent message is at least one segment, and zero would be
     * a claim `MessageCostLedger` refuses for the reason it refuses a zero cost
     * — *"a different and false statement from not knowing"*.
     */
    public static function count(string $body): int
    {
        $length = mb_strlen($body);

        if ($length <= 160) {
            return max(1, (int) ceil($length / 160));
        }

        return (int) ceil($length / 153);
    }
}
