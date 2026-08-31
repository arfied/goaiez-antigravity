<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What authorised a suppression to be lifted.
 *
 * ⚠️ THE ENUM IS THE GATE, AND ITS VALUE IS THE CASE THAT IS ABSENT. There is no
 * `ConsentCapture` case here, so "the customer ticked the box again" cannot be
 * passed to `lift()` at all — not because a caller is trusted to remember, but
 * because there is nothing to pass. Decision 285's shape: the chokepoint is a
 * type, so the unsafe path is a compile error rather than a missing line.
 *
 * ⚠️ WHY A RE-CONSENT IS NOT ON THIS LIST, WHICH WILL READ AS THE MISSING CASE.
 * `OptOut`'s own docblock used to promise it — *"consent is re-granted by a new
 * consent record"* — and `ConsentService::decide()` has never worked that way
 * (1020). The reason to keep it that way is decisions 334–337: `/f/{slug}` is
 * public and unauthenticated, and that surface has **already** been found writing
 * consent against identifiers the submitter did not own. A consent capture that
 * lifted a suppression would let anybody who knows a suppressed person's email
 * un-suppress them by filling in a form. The lift has to be carried by something
 * that proves control of the channel, or by a person who can be named.
 */
enum LiftSource: string
{
    /**
     * A START, UNSTOP or YES keyword arriving on the same number that carried
     * the STOP.
     *
     * This is the channel-authenticated case, and the authentication is
     * incidental rather than designed: the message came *from* the number, so
     * whoever sent it controls it. Carrier rules require honouring it.
     *
     * ⚠️ Nothing writes this yet — row 4's inbound webhook is its first caller,
     * and it is behind 10DLC brand registration. It ships because the alternative
     * is `OperatorAction` being the only case, which would make a lift look like
     * an administrative act rather than the customer's own.
     */
    case CarrierStart = 'carrier_start';

    /**
     * A named human cleared it, having established the request some other way —
     * a phone call, a reply to an email, a support conversation.
     *
     * ⚠️ THE ACTOR IS THE WHOLE CONTROL HERE. There is no evidence in the system
     * that the customer asked; what there is, is somebody's name against the
     * decision and an `audit_log` row that outlives them (297). That is weaker
     * than a START and is written down as weaker, because the alternative — no
     * operator path at all — strands every customer who asks by any means other
     * than texting the word START.
     */
    case OperatorAction = 'operator_action';
}
