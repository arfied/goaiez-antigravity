<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a short link was minted for — T137 `SL-5`.
 *
 * ⚠️ **A CASE JOINS THIS ENUM WHEN SOMETHING MINTS IT, NEVER BEFORE.** That is
 * `CustomerTimeline`'s rule for its union (1222) applied to a vocabulary: a case
 * with no minter is a value that can only ever appear in a test, and it makes
 * every `match` over this enum carry a branch nothing reaches. The two below are
 * the two consumers T137 names — *"used by SMS (159 law) + email"* — and the
 * enum stays this short until a third caller actually exists.
 *
 * ⛔ **NOT `OutreachPurpose`.** That enum answers *marketing or transactional*,
 * which is a consent question with legal weight; this one answers *what does
 * this link do*. Collapsing them would put a link's destination on the axis the
 * TCPA lane is routed by.
 */
enum ShortLinkPurpose: string
{
    /**
     * The review invitation's destination — a feedback page or a review
     * destination, whichever the tenant's routing chose at send time.
     */
    case ReviewInvite = 'review_invite';

    /**
     * A direct link to `/f/{slug}`, for a message that is asking for feedback
     * rather than routing to a public review.
     */
    case FeedbackPage = 'feedback_page';

    /**
     * The tenant's own booking page, sent by agent skill 5 (T176 R12/R14).
     *
     * ⚠️ **THE MINTER EXISTS — THAT IS WHY THIS CASE DOES.** The rule at the top
     * of this file is that a case joins when something mints it, and P6's
     * `TenantLinks::shortLinkFor()` is what mints this case and the two after
     * it — one per `TenantLinkKind`, chosen by a `match` with no default. ⛔ **It is not
     * a claim that a booking happened**: R12 is explicit that the platform has no
     * calendar hook, so this names where the customer was sent and nothing more.
     */
    case TenantBooking = 'tenant_booking';

    /**
     * The tenant's own payment page, sent by agent skill 6.
     *
     * ⛔ **A CLICK ON THIS IS NOT A PAYMENT.** R12: paid status is the customer's
     * word, recorded unverified, and the owner checks their own system. Decision
     * 113's rule for review destinations, met again — no platform gives us a
     * completion callback, and a purpose value is not one either.
     */
    case TenantPayment = 'tenant_payment';

    /**
     * A document the tenant shares, sent by agent skill 7.
     */
    case TenantDocument = 'tenant_document';
}
