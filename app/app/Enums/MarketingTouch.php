<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of touch one outbound message is, for the send-collision arbiter.
 *
 * `S1`, staged 2026-08-08 and named in T137 `SL-2`'s campaign engine: *"one
 * marketing touch per contact per 24h, channel-aware, service-class exempt
 * (missed-call/chat replies + transactional never blocked), priority order
 * invite > recovery > reactivation > campaign"*.
 *
 * ## Why this is not `OutreachPurpose`
 *
 * ⚠️ **THEY ARE DIFFERENT AXES AND COLLAPSING THEM WOULD BREAK BOTH.**
 * `OutreachPurpose` answers *which law applies* — Do Not Call, the state
 * mini-TCPA windows, prior express written consent — and it has exactly two
 * cases on purpose. This answers *how many of our messages one person has had
 * today*, which is a courtesy-and-complaint question rather than a legal one.
 * A review invite is `Transactional` under the first axis and is very much a
 * touch under this one: an invite and a reactivation landing on the same
 * afternoon is precisely the collision `S1` exists to stop.
 *
 * ## Why service messages are absent rather than exempt
 *
 * ⛔ **THE T69 LAW.** A missed-call text-back and a reply in a conversation are
 * never held, by anything, at any hour — so they are not a low-priority case
 * here, they never ask the arbiter at all. Giving them a case would put the
 * question in front of somebody who could answer it wrongly; leaving them out
 * means the only way to hold one is to write new code that does it deliberately.
 */
enum MarketingTouch: string
{
    /** A request to review, after a completed interaction. Highest priority. */
    case ReviewInvite = 'review_invite';

    /** Reaching an unhappy customer after a low rating. */
    case Recovery = 'recovery';

    /** `REACT-1` — bringing a dormant customer back. */
    case Reactivation = 'reactivation';

    /** Anything else a tenant sends to a chosen audience. Lowest priority. */
    case Campaign = 'campaign';

    /**
     * Lower wins. The ladder `S1` names, expressed once.
     */
    public function priority(): int
    {
        return match ($this) {
            self::ReviewInvite => 1,
            self::Recovery => 2,
            self::Reactivation => 3,
            self::Campaign => 4,
        };
    }

    /**
     * Whether this touch would give way to one already recorded.
     */
    public function yieldsTo(self $other): bool
    {
        return $this->priority() >= $other->priority();
    }

    /**
     * The touch class of an `outreach_messages` row, or null when the row is
     * not a message to the contact at all.
     *
     * ⚠️ **THE UNKNOWN CASE COUNTS AS A TOUCH, AND THE DIRECTION IS THE
     * DESIGN.** `outreach_messages.purpose` is a free string on `DATA-MODEL`
     * §5.7's own vocabulary, and only one of its values has a writer today
     * (`review_request`, from `ReviewInviteSender`). A purpose this method has
     * not met is a message somebody built and did not classify — so it is
     * counted, at the lowest priority, rather than waved through. The
     * permissive default here would be an arbiter that silently stops
     * arbitrating as the vocabulary grows, which is decision 256's vacuous
     * gate with a person on the other end of it.
     *
     * ⚠️ **`owner_alert` IS THE ONE EXCLUSION AND IT IS EXCLUDED BY
     * DEFINITION**, not by judgement: it goes to the business owner on the
     * account relationship, never to the contact, so counting it would hold
     * back a customer's message because their business received one.
     *
     * ⚠️ **`reminder` IS `ReviewInvite` RATHER THAN A CLASS OF ITS OWN, AND THE
     * DIRECTION IS THE CONSERVATIVE ONE** (T176 P14). `DATA-MODEL` §5.7's
     * vocabulary has one `reminder` value for every kind of reminder this
     * schema anticipates, and the only one with a writer is the review-invite
     * follow-up ({@see ReviewInviteKind}). Classifying it as the *highest*
     * class can only make other touches give way to it, never the reverse —
     * `SendCollisionArbiter::touchesSince()`'s own over-statement, in the same
     * direction and for the same reason. Leaving it on the `default` arm would
     * have made a review-invite reminder a low-priority campaign touch, which
     * a reactivation would then have been free to land beside.
     */
    public static function forOutreachPurpose(string $purpose): ?self
    {
        return match ($purpose) {
            'owner_alert' => null,
            'review_request', 'reminder' => self::ReviewInvite,
            'triage' => self::Recovery,
            default => self::Campaign,
        };
    }
}
