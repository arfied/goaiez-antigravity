<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Campaigns\UnknownSendReconciler;
use App\Services\Sms\SendLogEntry;

/**
 * What the carrier's own log said about one message we handed it.
 *
 * ⛔ **THIS EXISTS BECAUSE TWO OPPOSITE-LOOKING ANSWERS AND ONE SILENCE WERE ALL
 * ONE BOOLEAN** (7500). The SMS driver's `REFUSED_GROUPS` names `REJECTED` and
 * `UNDELIVERABLE`, and on the **submission** path treating them alike is right:
 * either answer, in a response to a request we just made, means *"do not treat
 * this as sent"*. On the **log** path the question is different — *what became
 * of this message* — and one bit could not carry the answer, because a log line
 * with **no status at all** also arrived as `false` and was counted, logged and
 * reported as a carrier refusal.
 *
 * ## What the vendor actually says, read from the vendor
 *
 * ⚠️ **VERBATIM FROM `https://www.infobip.com/docs/essentials/response-status-and-error-codes`,
 * FETCHED 2026-08-22**, because the plausible reading of these two names is not
 * the documented one:
 *
 *   `PENDING` (1)        *"The message has been processed and sent to the next
 *                        instance, for example, a mobile operator."*
 *   `UNDELIVERABLE` (2)  *"The message has not been delivered."*
 *   `DELIVERED` (3)      *"The message has been delivered."*
 *   `EXPIRED` (4)        *"The message has been sent and has either expired due
 *                        to pending past its validity period … or the delivery
 *                        report from the operator has returned `EXPIRED` as a
 *                        final status."*
 *   `REJECTED` (5)       *"The message has been received but has either been
 *                        rejected by Infobip or the operator has returned
 *                        `REJECTED` as final status."*
 *
 * ⛔ **SO NEITHER GROUP MEANS WHAT ITS NAME SUGGESTS, AND THE SLICE THAT BUILT
 * THIS SET OUT TO ACT ON THE SUGGESTION** (7506). The brief for 7500 said
 * `UNDELIVERABLE` means *the carrier took the message and could not deliver it*
 * — which is true of `UNDELIVERABLE_REJECTED_OPERATOR` and
 * `UNDELIVERABLE_NOT_DELIVERED` and **flatly false of the third member of the
 * same group**: `UNDELIVERABLE_NOT_SENT` (31) is documented, in four words, as
 * *"The message has not been sent."* And the mirror image holds for the other
 * one: `REJECTED` does not establish that nothing entered the network, because
 * its own group description includes *"the operator has returned `REJECTED` as
 * final status"* — an operator can only reject a message it was given.
 *
 * ⛔ **THE CONSEQUENCE IS A REFUSAL, AND IT IS THE POINT OF THIS ENUM.** A group
 * name is not enough to promote a row to `CampaignRecipientStatus::Sent` — *"the
 * claim that somebody was texted"* — nor enough to state that nothing was sent.
 * **What both groups do establish is the thing an owner is owed: the message did
 * not arrive.** So {@see self::Undelivered} and {@see self::Declined} are kept
 * apart, because they are different facts and a later caller (7377(a)'s
 * re-opened audience) will need them apart, and **neither is acted on as though
 * the sub-status were known**.
 *
 * ## Why an enum and not the vendor's string
 *
 * ⛔ **THE VOCABULARY IS STILL READ IN EXACTLY ONE FILE.** The SMS driver maps
 * `status.groupName` onto these cases and nothing else may:
 * {@see SendLogEntry::$verdict} arrives already decided, for the reason that
 * class's docblock gives about `statusGroup`, and a lint in
 * `tests/Feature/Architecture/MessagingTest.php` holds the mapping to one file.
 * **This enum is the home the distinction needed; it is not a second copy of the
 * vocabulary** — no case here names a vendor string. Four named cases and a
 * `match` with no `default` also mean a fifth vendor state is a compile-time
 * conversation rather than a silent arm, which two booleans would not have been:
 * they leave `(false, false)` meaning *"no answer"* by accident and `(true,
 * true)` reachable in the type system.
 */
enum CarrierVerdict: string
{
    /**
     * The carrier had this message.
     *
     * `PENDING`, `DELIVERED` and `EXPIRED`, whose group descriptions each say in
     * their own words that the message was sent onward, plus any group name this
     * application does not recognise.
     *
     * ⚠️ **NEVER *"A HANDSET RECEIVED IT"***, which is the distinction
     * {@see SendLogEntry} and `SentText` both make and the one
     * `CampaignRecipientStatus::Sent` already carries: that status is written on
     * `SendOutcome::accepted()`, when the vendor has answered `PENDING_ACCEPTED`
     * and nobody knows whether it will ever arrive.
     *
     * ⚠️ **AN UNRECOGNISED GROUP NAME LANDS HERE, WHICH IS THE DRIVER'S EXISTING
     * POSTURE AND NOT A NEW ONE**: *"an unknown group name is treated as
     * accepted … because refusing on an unrecognised label would turn a vendor
     * adding a state into an outage."*
     */
    case Took = 'took';

    /**
     * The carrier's final word is that the message was not delivered.
     *
     * Group 2, whose whole description is *"The message has not been
     * delivered."*
     *
     * ⛔ **IT DOES NOT SAY WHETHER THE MESSAGE WAS EVER ACCEPTED, AND THE LOG
     * CANNOT BE MADE TO SAY SO.** Two of its three SMS members were submitted
     * and one — `UNDELIVERABLE_NOT_SENT` — was not, and a log read forty-eight
     * hours later shows only the group. **So {@see UnknownSendReconciler} does
     * not promote it and does not re-open it**: it records the answer, stops
     * asking, and tells the owner the one thing that is true of every member.
     *
     * ⚠️ **THIS IS THE CASE 7377(a) MUST NOT TREAT AS `Declined`.** A re-opened
     * audience would re-send to somebody whose message may well have been
     * accepted and charged, which is the outcome the whole `Unknown` mechanism
     * exists to prevent.
     */
    case Undelivered = 'undelivered';

    /**
     * The carrier's final word is that it rejected the message.
     *
     * Group 5 — *"received but … rejected by Infobip or the operator has
     * returned `REJECTED` as final status"* — which is the sender's own account
     * being refused far more often than not: an unregistered sender, a
     * blocklisted destination, a DND subscriber, an account out of credit.
     *
     * ⚠️ **IT IS THE LIKELIER OF THE TWO TO MEAN NOTHING ENTERED THE NETWORK AND
     * IT DOES NOT ESTABLISH IT**, because the operator can also return
     * `REJECTED` about a message it was given. Kept apart from
     * {@see self::Undelivered} because they are different facts and the caller
     * that will need them apart does not exist yet; acted on identically today,
     * because everything either group establishes is the same thing.
     */
    case Declined = 'declined';

    /**
     * The log entry carried no status at all.
     *
     * ⛔ **NOT AN ANSWER, AND IT USED TO BE READ AS A REFUSAL** (7501). The
     * vendor's own status page documents five general status groups and
     * *absent* is not among them, so the honest reading is the contract's: **a
     * question with no answer**, left exactly where it was and asked again next
     * hour. Reading it as
     * {@see self::Declined} would end a row's question on the strength of
     * nothing said, which is the same error as reading a missing key as *"never
     * sent"* one layer up — and under 7501 that error is now permanent, because
     * an answer writes a full stop.
     */
    case Unstated = 'unstated';
}
