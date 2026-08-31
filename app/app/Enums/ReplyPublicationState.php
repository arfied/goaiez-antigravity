<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Reviews\ReplyPublicationStatus;

/**
 * Why an approved reply is not on the listing, in the owner's words (1738).
 *
 * ⚠️ **THIS IS NOT A COLUMN AND MUST NEVER BECOME ONE.** Every case is derived
 * at render time from the row plus the live state of the integration, because
 * three of the four are facts about *now* rather than about the attempt: an
 * operator switching `gbp.zernio_enabled` on, or an owner reconnecting Google,
 * changes the true answer without touching `replies`. A stored reason would go
 * stale the moment the blocker cleared and would then tell somebody to fix a
 * thing they had already fixed.
 *
 * ⚠️ **THE FOURTH IS A FACT ABOUT THE PAST AND IT HAS ITS OWN COLUMN, WHICH IS
 * NOT THE SAME THING AS STORING THIS ENUM** (6720). `provider_declined_at`
 * records that a vendor round trip happened and came back a refusal — the same
 * kind of fact as `posted_at`, written on the same path. The *state* is still
 * derived, and the ordering below still decides which of the four is said.
 *
 * ⚠️ **AND IT IS A TRANSLATION, NOT AN ECHO** (1748). `replies.error_message`
 * is machinery — `29` §2 rule 47 keeps machinery off owner screens — so no
 * case here is ever a stored string. `MessageLog::explain()` is the precedent:
 * the raw string stays in the row for whoever debugs it, and the owner gets a
 * sentence written for them.
 *
 * ⛔ **AND THIS DOCBLOCK SAID `ReplyPublicationStatus` READS THAT COLUMN AS A
 * BOOLEAN, WHICH IT NO LONGER DOES AND SHOULD NEVER HAVE DONE** (6720).
 * `error_message` is written by refusals that never reached Google, so the
 * boolean it offered was not the boolean {@see self::NotAccepted} needed. See
 * that case.
 *
 * ⛔ **AND THE COUNT IN THE TWO PARAGRAPHS ABOVE WAS FOUR AND IS FIVE — BOTH
 * READINGS KEPT AND DATED** (6940). They read *"three of the four are facts
 * about *now*"* and *"the fourth is a fact about the past"*, which was true of
 * every case that existed when they were written. {@see self::PublishUnconfirmed}
 * is the second fact about the past, it has its own column, and the sentence
 * about derivation covers it unchanged: the ordering below still decides which
 * of the five is said, and none of them is stored.
 *
 * ⚠️ **NO CASE MAY PROMISE A FUTURE POST.** *"Waiting to go up on Google"*
 * would describe a retry loop this application does not have. What is true is
 * that a decision is recorded, nothing is published, and approving again is the
 * one thing that tries once more — 1755's ruling, and the reason every case
 * here is phrased in the past or the present tense.
 *
 * ⛔ **THE PREMISE UNDER THAT RULE CHANGED ON 2026-08-28 AND THE RULE DID NOT —
 * THE OLD WORDING IS KEPT HERE BECAUSE IT IS THE EVIDENCE** (11040). It read
 * *"`PostReplyJob` is dispatched once, by `ReviewReplies::approve()`, and
 * nothing re-dispatches it and nothing watches for a blocker clearing"*.
 * `reviews:retry-stranded-replies` now watches for exactly that and
 * re-dispatches. ⚠️ **It does not make any of these five sentences a promise**:
 * the sweep refuses `PublishUnconfirmed` and `NotAccepted` outright, refuses a
 * run this platform's own worker killed, and gives every other row **one**
 * attempt per owner decision — so *"we will put this up"* is still false of
 * four cases and still unbounded-sounding for the fifth. ⛔ **Changing a string
 * here is a ruling and not a tidy-up**: 6523 refused the future tense on this
 * screen's heading and 7048 records it as the owner's, unchanged, and 11044
 * raises the question rather than answering it.
 *
 * ⛔ **AND *"NOTHING IS PUBLISHED"* IS NO LONGER TRUE OF EVERY CASE — THE OLD
 * SENTENCE IS KEPT ABOVE BECAUSE IT IS THE EVIDENCE** (6941). It is true of
 * four of the five and is exactly what {@see self::PublishUnconfirmed} exists
 * to deny: on that arm something may well be published and this application has
 * no way to find out. **What survives whole is the half that carries the rule**
 * — no future tense, no promise of a post. ⚠️ **Its stated reason —
 * *"because nothing re-dispatches on any arm"* — is superseded by 11040 and
 * kept above; what replaces it is that the sweep re-dispatches on **one** arm,
 * once, and this case is not that arm.**
 *
 * ⛔ **THE COUNT MOVED AGAIN ON 2026-08-28 — FOUR, THEN FIVE, NOW SIX — AND
 * EVERY EARLIER READING ABOVE IS KEPT ON 6940's OWN RULE** (11200).
 * {@see self::NotEntitled} is the sixth, and it is the first case whose subject
 * is neither the reply, the integration nor the listing but **the account**:
 * `Subscriptions::isEntitled()`'s answer, asked at render time exactly as the
 * registry and the connection are. ⚠️ **Every sentence above survives it
 * unchanged** — it is derived rather than stored, it is a fact about *now*
 * rather than about the attempt, it promises no future post, and it is not the
 * one arm the sweep re-dispatches.
 *
 * ⚠️ **AND IT IS THE ONE CASE THAT WILL BE SAID TO SOMEBODY WHO HAS STOPPED
 * PAYING US**, which is why its next step is *start your plan* and never *start
 * your plan again*: the commonest way to reach it from 2026-08-25 is a no-card
 * trial running out on somebody who has never had a plan to restart (9332).
 */
enum ReplyPublicationState: string
{
    /**
     * Publishing replies is switched off platform-wide.
     *
     * ⚠️ **FIRST IN THE LADDER, AND THE ORDER IS THE POINT.** With
     * `gbp.zernio_enabled` false the owner cannot connect Google at all —
     * `Account\Connections` hides the control on the same key — so reporting
     * this row as *"Google is not connected"* would send them to a screen that
     * offers them nothing and blame them for an operator's switch.
     */
    case PublishingOff = 'publishing_off';

    /**
     * The plan behind this account is not running, so nothing publishes for it.
     *
     * ⛔ **THE ONLY CASE WHOSE SUBJECT IS THE ACCOUNT RATHER THAN THE REPLY, THE
     * INTEGRATION OR THE LISTING** (11200–11205). `Subscriptions::isEntitled()`
     * is the answer — the same one the monthly credit grant, the content
     * publisher, the tenant's own credit screen and the weekly owner digest all
     * use — and it is deliberately **generous**: `pending_checkout`, `trialing`
     * and `past_due` are all entitled, so this case is not reachable by a tenant
     * mid-trial or mid-retry. What reaches it is a cancelled or incomplete row,
     * a no-card trial whose days ran out, and — the population 8960–8979
     * records — an account whose own recorded `ends_at` has been and gone while
     * `status` sits at `active` for ever.
     *
     * ⚠️ **SECOND IN THE LADDER, DIRECTLY UNDER {@see self::PublishingOff}, AND
     * THE ORDER IS THE SAME ARGUMENT THAT CASE ALREADY MAKES.** With the
     * platform switch down nothing publishes for anybody, so telling an owner to
     * start a plan would sell them a subscription that would not have published
     * either. Above {@see self::NotConnected}, because reconnecting Google buys
     * an unentitled account nothing — the same reason `PublishingOff` outranks
     * that case.
     *
     * ⚠️ **{@see self::PublishUnconfirmed} STILL OUTRANKS IT**, on that case's own
     * argument: every sentence below it asserts a negative about a third party's
     * listing, and an unconfirmed reply may be public right now. A lapsed plan
     * does not make that assertion any safer.
     *
     * ⚠️ **IT IS DERIVED, LIKE EVERY OTHER CASE, AND IT IS THE MOST MOVEABLE OF
     * THEM** — `Subscription::accessHasEnded()` is a clock read, so this card
     * appears on its own the day a term runs out and goes away the hour somebody
     * resubscribes, with nothing written to `replies` either way.
     */
    case NotEntitled = 'not_entitled';

    /** Google is not connected for this reply's location, which the owner can fix. */
    case NotConnected = 'not_connected';

    /**
     * We reached Google and the reply did not land.
     *
     * ⛔ **THE ONLY CASE THAT SPEAKS FOR A THIRD PARTY, WHICH IS WHY IT IS THE
     * ONLY ONE WITH EVIDENCE BEHIND IT** (6720, closing 6685). It read
     * `replies.error_message` for its presence, and that column is written by
     * refusals made **before anything reached Google** —
     * `PostReplyJob::handoff()`'s `integration_disabled` and `not_connected`
     * arms. Every one of those rows said this sentence the moment
     * `gbp.zernio_enabled` was switched on and the location was connected.
     *
     * It now derives from `replies.provider_declined_at`, whose sole writer is
     * `ReviewReplies::markDeclinedByProvider()` — unreachable without a
     * `GbpRequestFailed` the vendor's own response produced, and refusing a
     * retryable or a revoked-grant one. A row that never left this application
     * has nothing to reach this case with.
     */
    case NotAccepted = 'not_accepted';

    /**
     * We asked Google and never got an answer, so nobody here knows.
     *
     * ⛔ **THE ONLY CASE THAT REFUSES TO SAY WHETHER THE REPLY IS ON THE
     * LISTING, AND IT IS FIRST IN THE LADDER FOR THAT REASON** (6942, closing
     * 6828). `replies.publish_unconfirmed_at` is written by
     * `ReviewReplies::markPublishUnconfirmed()` when a request reached Zernio
     * and nothing came back — a reset, a read timeout, a socket that went away
     * — and Laravel raises one exception for *"we never sent it"* and for *"we
     * sent it and lost the answer"*, so from here the two are the same fact.
     *
     * ⛔ **EVERY OTHER CASE ENDS IN A CLAIM THIS ROW CANNOT SUPPORT.** *"this
     * has not gone up"*, *"this could not be published"*, *"Google did not take
     * it"* and *"This is not on Google."* are four ways of asserting a
     * negative about a third party's listing, and an unconfirmed reply may be
     * on it right now. **So this arm outranks even `PublishingOff`**, whose own
     * first-place argument is about not sending an owner to a screen that
     * offers them nothing — a real argument against arms 2 to 4 and not against
     * this one, because the action here needs nothing from this platform. The
     * owner opens Google and looks, which they can do with the integration off,
     * their connection revoked and this application unreachable.
     *
     * ⚠️ **THE SENTENCE IS THE ONE THE ACTIVITY FEED ALREADY USES** (6824). An
     * owner who reads *"We sent a reply to Google and did not get an answer
     * back. Check that review on Google — if the reply is not there, approve it
     * again."* in their feed and something differently-worded here would have
     * two problems rather than one.
     *
     * ⚠️ **AND IT IS STILL A TRANSLATION, NOT AN ECHO** (1748).
     * `markPublishUnconfirmed()` also writes an English sentence into
     * `error_message`, which is close enough to this one to look interchangeable
     * and is not: that column is machinery by construction — its other writers
     * put vendor codes in it — and rule 47 keeps the whole column off owner
     * screens rather than the strings somebody judged unsafe.
     *
     * ⛔ **THIS CASE USED TO BE PERMANENT AND IS NOW PERMANENT FOR ONE
     * POPULATION ONLY** (6955, closed at 7120–7139).
     * `ReviewReplies::reconcileUnconfirmedPublication()` clears the column when
     * the provider reports **no** owner reply on the review, so a reply that
     * never landed leaves this case on evidence rather than on the owner's
     * guess. A review that *does* carry an owner reply stays here for ever, and
     * deliberately: `hasReply` is `true` for a stranger's reply, for one typed
     * into Google's own dashboard, and for one of ours that Google rejected —
     * `ReviewReply.reviewReplyState` is `PENDING`/`REJECTED`/`APPROVED` on
     * Google's own reference (read 2026-08-21) and Zernio relays none of it.
     * **The sentence stays because it is still the true one**, which is the
     * whole of what this case is for.
     */
    case PublishUnconfirmed = 'publish_unconfirmed';

    /**
     * Nothing is recorded against this reply either way.
     *
     * ⚠️ **DELIBERATELY THE VAGUEST STRING OF THE FOUR, BECAUSE IT COVERS TWO
     * THINGS THE ROW CANNOT TELL APART**: a job still sitting in the queue
     * seconds after approval, and an attempt that returned `unavailable` from
     * `PostReplyJob::execute()` — which writes **nothing** to the row (6528).
     * Inventing "waiting to send" for both would be right about the first and a
     * lie about the second, so the card shows the approval date instead and
     * lets a three-week-old one speak for itself.
     *
     * ⛔ **IT COVERS A THIRD THING SINCE 2026-08-21 AND THE THIRD IS THE ONE
     * THE SENTENCE IS ACTUALLY TRUE OF** (7130). `ReviewReplies::reconcileUnconfirmedPublication()`
     * clears `publish_unconfirmed_at` when the provider reports no owner reply
     * on the review, so such a row lands here — and for it *"This is not on
     * Google."* is not a guess at all: it is the last thing Google said.
     * ⚠️ **The card cannot tell that row from the other two and this slice does
     * not let it**, because the evidence is a report with a time and the row
     * keeps no record of when it was read. Saying *"we checked and it is not
     * there"* would need a column, and a column claiming an observation is what
     * `ReplyPublicationStatus` may not derive from `error_message` (6720, 6949).
     * ⚠️ **So the string is unchanged and it is now under-stated rather than
     * vague for one of the three** — the honest direction, and recorded rather
     * than fixed.
     */
    case NotPublishedYet = 'not_published_yet';

    /**
     * The one line the owner reads, in outcome language (`22` §3.4).
     *
     * Never a vendor string, never a status name, and never a colour on its own
     * — the card renders this as plain text beside the approval date.
     */
    public function label(): string
    {
        return match ($this) {
            self::PublishingOff => 'Publishing replies to Google is switched off on our side, so this has not gone up.',
            self::NotEntitled => 'Your plan is not running, so this has not gone up.',
            self::NotConnected => 'Google is not connected for this location, so this could not be published.',
            self::NotAccepted => 'We tried to publish this and Google did not take it.',
            self::PublishUnconfirmed => 'We sent this to Google and did not get an answer back, so we do not know whether it is on your listing.',
            self::NotPublishedYet => 'This is not on Google.',
        };
    }

    /**
     * What the owner can do about it, or null when the answer is nothing.
     *
     * ⚠️ **`PublishingOff` RETURNS NULL ON PURPOSE.** A platform switch is not
     * the owner's to flip, and *"contact support"* would manufacture a ticket
     * out of a state they cannot influence — `CLAUDE.md`'s first tiebreaker is
     * less support surface. Saying nothing is the honest end of that sentence.
     *
     * ⛔ **`PublishUnconfirmed` LEADS WITH *LOOK* AND NOT WITH *APPROVE AGAIN*,
     * AND THE ORDER OF THOSE TWO CLAUSES IS THE WHOLE OF IT** (6943). Approving
     * again is a real retry — `ReviewReplies::approve()` clears the column
     * before it dispatches, so `PostReplyJob`'s pre-vendor guard does not refuse
     * it — but on this arm it may be a retry of something that already
     * succeeded. **6937 narrows what that costs and does not make it free**:
     * Google's `updateReply` is a `PUT` that creates if nothing is there, so the
     * customer still sees one reply, and what repeats is a second notification
     * to the reviewer, a reply timestamp that moves, and a vendor call. The one
     * party who can tell the two apart is the owner, with the listing open, so
     * they are asked to look first.
     */
    public function nextStep(): ?string
    {
        return match ($this) {
            self::PublishingOff => null,
            self::NotEntitled => 'Start your plan, then approve this reply again.',
            self::NotConnected => 'Connect Google for this location, then approve this reply again.',
            self::NotAccepted => 'Edit the wording and approve it again, or leave it as it is.',
            self::PublishUnconfirmed => 'Check that review on Google. If the reply is not there, approve it again.',
            self::NotPublishedYet => 'Approving it again asks Google one more time.',
        };
    }
}
