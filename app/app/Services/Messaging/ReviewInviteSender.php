<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\InviteAttemptStatus;
use App\Enums\MarketingTouch;
use App\Enums\MessageCostKind;
use App\Enums\MessagingLane;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\OutreachStatus;
use App\Enums\ReviewInviteKind;
use App\Enums\SendRefusalReason;
use App\Enums\ShortLinkPurpose;
use App\Exceptions\CreditMovementRefused;
use App\Models\Customer;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\OutreachMessage;
use App\Models\Review;
use App\Notifications\ReviewInviteEmail;
use App\Notifications\ReviewInviteReminderEmail;
use App\Services\Billing\EmailCredits;
use App\Services\Billing\MessageCostLedger;
use App\Services\Billing\MessageRates;
use App\Services\Billing\SendCredits;
use App\Services\Campaigns\SendCollisionArbiter;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SendPermit;
use App\Services\Destinations\InviteOption;
use App\Services\Destinations\ReviewInvites;
use App\Services\Mail\PlatformMailer;
use App\Services\Messaging\Outbound\SendKey;
use App\Services\Messaging\Outbound\SendSettlement;
use App\Services\Messaging\Outbound\SmsSegments;
use App\Services\ShortLinks\ShortLinkClicks;
use App\Services\ShortLinks\ShortLinks;
use App\Services\Sms\PlatformTexter;
use App\Support\LegalCanon;
use App\Support\SqlState;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The first message this application has ever sent to somebody else's customer.
 *
 * `17` FPR-04's email channel, and — as of row 4 slice 4 — its SMS channel.
 * Everything before this went to an account holder (a sign-in link, a support
 * notice) where the account relationship is the authorisation. This is the other
 * kind, and the difference is the entire compliance surface of the product.
 *
 * ⚠️ **FIVE GATES PER CHANNEL, AND EVERY ONE OF THEM CAN REFUSE ALONE.**
 * `BUILD-PLAN` §2.10.4 asks for exactly that — *"every one of the five gates
 * driven red alone … 398's rule, because an outer gate refusing first makes the
 * inner one unfalsifiable."* They are in this order because each is cheaper and
 * more absolute than the next:
 *
 *   1. **The feature kill switch** — `review_invite.email_enabled` on email,
 *      `review_invite.sms_enabled` on SMS, both seeded **false**. One registry
 *      read, no query, and it is the only gate that can refuse before anything
 *      about this customer has been looked at. Email waits on open question H,
 *      bounce and complaint handling (714). **SMS waits on the 10DLC campaign
 *      being approved** — the brand cleared (1562), the campaign was filed and
 *      REJECTED (11617), and until one is approved a carrier filters the
 *      message silently.
 *   2. **Something to invite them to.** `ReviewInvites::offerFor()` returning an
 *      empty list is an ordinary state — a below-threshold rating, every
 *      destination disabled, a cleared `place_id` (382) — and a message saying
 *      "please review us" with no link in it is worse than no message.
 *      ⚠️ **Before the permit, and that ordering is load-bearing**: a permit is a
 *      record of a decision, and minting one we are not going to act on writes a
 *      misleading trail.
 *   3. **Not already invited.** Below — and the two channels ask a *different*
 *      question, deliberately (1605).
 *   4. **`ConsentService::permit()`**, which is the one that matters and the one
 *      that cannot be skipped, because both `PlatformMailer::sendToCustomer()`
 *      and `PlatformTexter::sendToCustomer()` take the permit as a required
 *      parameter (285, restated for SMS as 1566). Behind it sit suppression, the
 *      platform-wide `opt_outs` of 424–427, the Do Not Call and litigator
 *      registers, and the state mini-TCPA windows.
 *   5. **The channel switch.** `sms.enabled` for SMS, read inside
 *      {@see PlatformTexter} rather than here, and `PlatformMailer`'s own
 *      deliverability guard for email. ⚠️ **It is named as a gate even though it
 *      is not in this file**, because a reader counting the checks in this class
 *      would find four and conclude the fifth had been lost. Re-reading
 *      `sms.enabled` here would be a second implementation of one rule and the
 *      two could disagree — `ReviewInvites`' own argument about
 *      `send_review_requests`, one domain over.
 *
 * ⛔ **THE EMAIL HALF OF GATE 5 IS ASKED IN THIS FILE SINCE 10040, AND THE
 * PARAGRAPH ABOVE IS NARROWED RATHER THAN OVERTURNED.** *"Not in this file"* is
 * still true of `sms.enabled` and is no longer true of the mail transport:
 * `sendEmail()` asks {@see PlatformMailer::customerMailRefusal()} before it
 * opens its transaction. ⚠️ **THAT IS NOT A SECOND IMPLEMENTATION AND THE
 * DISTINCTION IS THE WHOLE ARGUMENT** — `sms.enabled` re-read here would be a
 * second *reading of a rule*, two answers to one question; this is the same
 * method `PlatformMailer` asks itself, called one gate earlier, so there is
 * exactly one implementation and it cannot disagree with itself. **What moved is
 * where it is asked, never who decides.**
 *
 * ⚠️ **THE SMS PATH TREATS GATE 5 AS A REFUSAL AND ROLLS THE ROW BACK WITH IT.**
 * `PlatformTexter` returns null when the channel cannot carry the message, which
 * is an operator or a registration stopping the channel rather than a send
 * failing — so the `outreach_messages` row must not survive it. Keeping it would
 * leave a row claiming we queued a message that will never be sent, and gate 3
 * would then refuse that customer an invite **forever**, silently, because an
 * operator switched the channel off for an hour. See `sendText()`.
 *
 * ⛔ **"WHEN `sms.enabled` IS OFF" WAS THIS SENTENCE UNTIL 2026-08-26 AND IT
 * NAMED ONE OF TWO STATES — BOTH READINGS KEPT AND DATED** (4368).
 * {@see PlatformTexter::sendToCustomer()} has returned null on a **second**
 * condition since decision 2550: a null number selection over a **non-empty**
 * inventory, meaning every number is quarantined, retired or still registering.
 * ⚠️ **This file said the switch was the only cause in two places**, here and in
 * `sendText()`'s own comment, which is 10048's shape — a census read as
 * exhaustive, wrong in the direction of reassurance — in the file 10048 was
 * written about. ⚠️ **And the second state is the one that can fire today**:
 * `sms.enabled` is on.
 *
 * ⛔ **THE REFUSAL IS A VALUE NOW, AND THE ROLLBACK IS UNTOUCHED** (10190).
 * `send()` and `remind()` are the lossy views of {@see self::attempt()} and
 * {@see self::attemptReminder()}, which answer an {@see InviteAttempt} carrying
 * the {@see SendRefusalReason} that refused. **Nothing about what is rolled back
 * changed** — an unsent invite must still leave no spent credit, no live tracked
 * link and no row claiming a send. What changed is that the explanation is
 * computed before `rollBack()` and returned after it, because a value is the
 * only thing a rollback cannot take.
 *
 * ⚠️ **`autopilot_settings.send_review_requests` IS NOT CHECKED HERE, AND THAT
 * WILL READ AS THE MISSING GATE.** It was in the first version of this class.
 * `ReviewInvites`' own docblock is why it came out: decision 381 has
 * `ReviewRouter` empty the offered list when an owner switches solicitation off,
 * so the toggle reaches here **through the snapshot**, and re-reading it would
 * be *"a second implementation of `29` §2 rule 40's gate, and the two could
 * disagree"*. Gate 2 is that gate. A tenant with solicitation off has an empty
 * `routed_destinations`, `offerFor()` returns `[]`, and nothing is composed —
 * which is also why the test for it asserts on the toggle rather than on this
 * file's line count.
 *
 * ⚠️ **A SIXTH GATE SITS ABOVE ALL OF THEM AND IS NOT IN THIS FILE EITHER**: a
 * paused tenant. `AutopilotJob::handle()` refuses before `SendReviewInviteJob`
 * ever reaches this class, and records `skipped` with a reason (821–823).
 * ⛔ **THAT ONE IS `TenantPause`, WHICH IS A DIFFERENT TABLE FROM THE ONE THE
 * CONTAINMENT BELOW READS, AND THE DIFFERENCE IS THE WHOLE OF 2970.** 2102's
 * automatic complaint-rate trip writes a `SendingPause` row and never a
 * `TenantPause` one, so an autopilot pause is an owner-visible "we stopped your
 * automations" and says nothing at all about whether this platform may send.
 * Reading the sixth gate as covering the containment is exactly the mistake the
 * campaign runner made before 2571.
 *
 * ## The containment — {@see SendingGuard}, per channel, before any write (2970)
 *
 * ⛔ **THIS CLASS CONSULTED NONE OF IT FROM 2026-08-04 UNTIL 2026-08-12, SO
 * THROWING THE GLOBAL PLATFORM HALT DID NOT STOP A SINGLE REVIEW INVITE.** 2903
 * recorded the gap in as many words and deferred it; this is the slice that
 * closes it. The guard answers three questions in one call and any of them
 * refusing means no message goes:
 *
 *   - **`messaging.global_halt`** — T137 `SL-8`'s platform switch, whose own
 *     registry description is *"stops ALL outbound messaging on the platform,
 *     for every tenant and **every channel**"*. It is thrown by an operator on
 *     the sending-controls screen **and automatically** by
 *     `messaging:watch-platform-complaint-rate` (2400–2410, armed at 2684), so
 *     this is not a switch somebody is standing next to.
 *   - **This tenant's live `SendingPause`** — the durable state an operator
 *     resumes, retained after release as the incident series (2119a).
 *   - **2102's automatic complaint-rate trip**, which is why the call sits on
 *     the hot path per message rather than once per batch: the complaints
 *     arrive *because* messages are going out, so a run trips itself partway
 *     through or the trip is decoration.
 *
 * ⚠️ **BOTH CHANNELS, AND EMAIL IS NOT AN OVERSIGHT.** The halt says *every
 * channel* and a `SendingPause` stops *every message for a tenant* — its own
 * audit entry says so.
 *
 * ⛔ **"WHAT EMAIL DOES NOT HAVE IS A RATE" WAS TRUE AND IS NOT — CORRECTED
 * 2026-08-20 (6360).** This continued: *"`SendingHealth` writes no window for
 * `OutreachChannel::Email` because nothing maps an SES bounce back to a tenant,
 * so `shouldTrip(Email)` reads an empty window, finds no volume and can never
 * fire. **The email call is carrying the halt and the pause, not a threshold.**"*
 * **All four email counters now have writers** — the send is settled on
 * Laravel's `MessageSent` event and SES `Delivery`, `Bounce` and `Complaint`
 * events are counted against the tenant that sent the message — so the call
 * below carries the halt, the pause **and** 2102's trip, on both channels.
 * ⚠️ **No real SES event has ever reached this application** (open question H),
 * so on today's deployment the email window is still empty for want of traffic
 * rather than for want of a mechanism.
 *
 * ⚠️ **BEFORE THE PERMIT AND BEFORE `beginTransaction()`, IN BOTH CHANNEL
 * METHODS.** Nothing is written at that point, so a refusal costs one query and
 * no rollback — `PlatformMessageSender`'s step 2, for its reason. It is
 * deliberately *not* hoisted into `send()`: the guard answers per channel, and
 * a single call up there would have to name a channel before this class knows
 * which one it is sending on.
 *
 * ⚠️ **THE FIVE-GATE NUMBERING ABOVE IS UNCHANGED ON PURPOSE.** Three test
 * files and `BUILD-PLAN` §2.10.4 cite those gates by number; renumbering them
 * to slot this in would silently repoint every one of those citations at a
 * different check. The containment is a layer over the five, not a sixth
 * member of them.
 *
 * ✅ **THE `SendKey` CLAIM IS NOW HERE ON BOTH CHANNELS, AND SO IS THE COST
 * BOOK** (2975, 2976, 3730; SMS at 4802/4803, email at 4920).
 * ⛔ **THIS PARAGRAPH SAID THE OPPOSITE UNTIL 2026-08-18 AND BOTH READINGS ARE
 * KEPT**, because the gap it described was real for months and its shape is the
 * one `CLAUDE.md` warns about most: it read *"the idempotency claim remains
 * `PlatformMessageSender`'s alone"* and *"the review invite's own SMS still
 * reaches no cost book"*, which was true, then half-true for six days after
 * 4803 closed the SMS half, and is now neither. **A gap that is written down is
 * still a gap; a closed gap still written down is 2505.**
 * ✅ **The internal cost book is written for both channels** — see
 * `recordCost()`, which prices SMS through {@see SmsSegments} (the extraction
 * 4800 made rather than the copy 2976 refused) and email with no segment count
 * at all, because an email is one email however long it is.
 * ✅ **The claim is {@see self::inviteSendKey()}, shared by both channels**, for
 * that same anti-copy reason applied to the occasion string.
 * ⚠️ `PlatformMessageSender` is named in prose rather than with a `{@see}` for
 * this file's own recorded reason — Pint promotes one into a real `use`, and an
 * import would read as an intent to call something this class deliberately does
 * not (2908). `MessageCostLedger` **is** imported now, because this class calls
 * it.
 *
 * ## The credit — the SMS channel from 2900, and email from 3299
 *
 * ⛔ **UNTIL 2900 THIS CLASS SENT EVERY REVIEW-INVITE TEXT FOR FREE.** T137 R9
 * and decision 2060 put review invites, missed-call text-back and the chat bot
 * on **one shared SMS balance at one credit per send**;
 * ⚠️ **the *quantity* in that sentence was reversed at 9182** — one credit for
 * the text and one for the media — **and this path is unaffected, because it
 * composes no media**: a review-invite text is one credit under both rules. The
 * shared balance, which is what this paragraph is about, is unchanged.
 * {@see SendCredits::debitForSend()} implements that and
 * had exactly one caller — `PlatformMessageSender`, reached only from
 * `RunCampaignJob`. This path opened its own transaction, wrote its own row and
 * called the texter directly, so it debited nothing. Worse, the other sender's
 * docblock announced the gap as *closed* (*"every SMS this system could send was
 * free"*), which is `docs/FAILURE-SHAPES.md`'s "protection layer asserted
 * before it is true"
 * and is why nobody looked here for months.
 *
 * ⚠️ **THE DEBIT IS INSIDE THE EXISTING TRANSACTION AND THE ORDERING AROUND IT
 * IS UNCHANGED** (2901). `debitForSend()` takes the `OutreachMessage::create()`
 * as its closure, so the row and the debit are one outcome — and the short-link
 * mint still happens **before** either, still inside `beginTransaction()`, which
 * is the ordering the comment at the top of `sendText()` exists to protect. A
 * refusal from the texter, a throw, or an exhausted balance all roll back the
 * same three things: the link, the row, and the credit.
 *
 * ⛔ **EMAIL DEBITED NOTHING UNTIL 2026-08-13, AND THAT WAS DELIBERATE RATHER
 * THAN AN OVERSIGHT** (2902). The paragraph that stood here read: *"R9's credit
 * is the **SMS** balance; email is metered separately at $20/10,000 (2071)
 * against a meter that is not built. Debiting an SMS credit for an email would
 * charge the wrong pool for the wrong product and would make the one shared
 * balance mean something different depending on which channel happened to win
 * the `send()` race above."*
 *
 * ✅ **THE METER IS BUILT AND THE EMAIL IS NOW DEBITED —
 * {@see EmailCredits::debitForSend()}, at $20 per 1,000** (3299, a tenfold
 * correction of 2063/2071 taken from the top-up SKUs rather than the headline).
 * 3297 is what makes it urgent rather than tidy: with the per-tenant dollar cap
 * deleted (3293) the credit balance is the **only** ceiling, so a cost path that
 * debits nothing is bounded by nothing at all — and a path with no debit does
 * not look uncapped, **it looks free**.
 *
 * ⚠️ **2902's OBJECTION IS CARRIED RATHER THAN ANSWERED, AND IT IS STILL TRUE.**
 * It was about *which pool*, never about whether an email costs something, and
 * `credit_ledger` still holds one undifferentiated balance — so today an email
 * unit really does come out of the balance a text spends from. `EmailCredits`
 * holds that seam in one method and says so in as many words; when the ledger
 * learns which product and which pool a unit belongs to (3298, 3307), nothing in
 * this file changes.
 *
 * ⚠️ **AN EXHAUSTED BALANCE REFUSES THE EMAIL AND NEVER THROWS**, which is the
 * same `CreditMovementRefused` degradation `sendText()` has carried since 2900
 * and for the same reason — 2904, and rule 43's surviving half (3294).
 *
 * ✅ **THE FIRST THIRD OF 2903 IS CLOSED: THIS PATH NOW HAS {@see SendingGuard}**
 * (2970). ⚠️ **The sentence above said "no `SendingGuard`, no `SendKey` claim and
 * no cost ledger" and was true for eight days**, which is worth leaving visible:
 * a gap that is written down is still a gap, and this one made the global halt
 * inapplicable to the only send path a tenant could actually reach.
 * ✅ **The other two thirds are closed too** — the cost ledger for SMS at 4802,
 * the `SendKey` claim for SMS at 4803 and for email at 4920. **2903 is closed
 * entirely**, and the eight days between the ruling and the code are why the
 * sentence above is kept rather than deleted.
 *
 * ## One invite, one channel
 *
 * ⚠️ **EMAIL IS ATTEMPTED FIRST AND SMS ONLY IF EMAIL SENT NOTHING** (1604), and
 * the order is not a claim about which channel works better. It is the order
 * that leaves the settled path exactly as it was: email's gates, its
 * `alreadySent()` question and its behaviour are byte-for-byte what they were
 * before this slice, and SMS is reached only where email declined. Gate 3 on the
 * SMS side then asks *"has this customer been invited on **any** channel"*, so
 * the pair is mutually exclusive and nobody is contacted twice about one review.
 *
 * ⚠️ **`OutreachPurpose::Transactional` IS PASSED EXPLICITLY ON BOTH CHANNELS,
 * and the default would have been wrong in the safe direction.** `permit()`
 * defaults to `Marketing` (486), which refuses every send in an unconfigured
 * install — `RegistryNotLoaded` fires until all three scrubbing registers are
 * imported (487, 1600), and `StateUnknown` fires for any contact whose state
 * nobody has recorded. ⚠️ **`customers.region_code` has a writer as of row 4
 * slice 5 (1594), so the second refusal is no longer unconditional** — which
 * makes this explicit argument load-bearing rather than merely tidy: omitting it
 * once cost nothing because marketing was refused outright, and omitting it now
 * would send a review invite under a classification `24` §3.3 does not support.
 * `24` §3.3 is the authority, and the copy in `ReviewInviteEmail` and in
 * `compose()` below is the condition that keeps it true.
 *
 * ## And one follow-up, once — T176 P14
 *
 * ⚠️ **{@see self::remind()} IS A SECOND MESSAGE AND EVERY SENTENCE ABOVE STILL
 * GOVERNS IT.** It reaches the same `sendEmail()`/`sendText()` bodies with a
 * different {@see ReviewInviteKind}, so the five gates, the containment, the
 * permit, the debit, the mint, the settlement and the cost book are the same
 * code rather than a copy of it. What it adds is two gates the immediate invite
 * deliberately does not have — the send-collision arbiter and the recipient-local
 * legal daytime window — and both live in `remind()` rather than in the send
 * bodies, so that a reader counting the checks in `sendEmail()` still finds
 * exactly the invite's set.
 *
 * ⛔ **THE FIVE-GATE NUMBERING IS UNCHANGED AGAIN, FOR THE CONTAINMENT'S OWN
 * REASON.** Three test files and `BUILD-PLAN` §2.10.4 cite those gates by
 * number.
 *
 * ⚠️ **AND OUR PURPOSE IS NOT THE CARRIER'S USE CASE — THE 10DLC CAMPAIGN IS
 * FILED AS *MIXED* WHILE THIS STAYS `Transactional`** (1602). They are two
 * different axes and neither is evidence about the other: `OutreachPurpose`
 * selects which of *our* consent checks, suppression registers and mini-TCPA
 * windows apply, and the campaign use-case tells a carrier what traffic to
 * expect on the number. Filing the campaign as single Customer Care to "match"
 * this classification is the misclassification risk the 10DLC research
 * reference warns against by name.
 */
final class ReviewInviteSender
{
    public function __construct(
        private readonly ConsentService $consent,
        private readonly ReviewInvites $invites,
        private readonly PlatformMailer $mailer,
        private readonly PlatformTexter $texter,
        private readonly DefaultsRegistry $defaults,
        private readonly ShortLinks $links,
        private readonly SendCredits $credits,
        private readonly EmailCredits $emailCredits,
        private readonly SendingGuard $guard,
        private readonly SendSettlement $settlement,
        private readonly MessageCostLedger $costs,
        private readonly MessageRates $rates,
        private readonly SendCollisionArbiter $arbiter,
        private readonly ShortLinkClicks $clicks,
        private readonly MessageLog $messages,
    ) {}

    /**
     * Invite this customer to post publicly, or decline to and say nothing.
     *
     * Returns the logged message, or null when any gate refused. **Null is not
     * an error and is never surfaced to the customer** — they have already
     * submitted their feedback and seen the on-screen picker, which is the path
     * that works with no message at all (`BUILD-PLAN` §2.6.4 conflict 2).
     *
     * ⛔ **THIS IS THE LOSSY VIEW OF {@see self::attempt()}, AND IT IS EXACTLY
     * THE PAIR {@see ConsentService::permit()} AND {@see ConsentService::decide()}
     * ALREADY ARE.** One traversal produces both, so the answer and the
     * explanation cannot disagree — `SendDecision`'s argument, and the reason
     * that pair is safe. ⚠️ **What was never safe is a *caller* choosing the
     * lossy one when it needs the reason**, which is what this class did to
     * `permit()` on both channels for months.
     *
     * ⛔ **ITS TWO CALLERS WERE `SendReviewInviteJob` AND, THROUGH
     * {@see self::remind()}, `SendInviteReminderJob`, AND BOTH NOW ASK
     * `attempt()`/`attemptReminder()` INSTEAD — 10211, PHASE 1.** They write
     * the `automation_runs` row, which is the only durable record this path
     * produces, and it said `invited: false` / `reminded: false` for every one
     * of eleven distinct outcomes until they were repointed. **It was not a
     * three-line change in each**: the return type changed shape under both
     * callers, so each grew a `match` over four states rather than a
     * `!== null` comparison. ⚠️ **This method is kept anyway, and is not
     * permanent furniture by accident** — `RefusedInviteNamesItsRuleTest`'s
     * own `send() still answers the caller in nulls` pins it as the
     * compatibility shim it is: nothing else in `app/` calls it, and a lane
     * finding a third caller should ask whether that caller wants the reason
     * too before reaching for the lossy one.
     */
    public function send(
        Review $review,
        Location $location,
        FeedbackPage $page,
        Customer $customer,
    ): ?OutreachMessage {
        return $this->attempt($review, $location, $page, $customer)->message;
    }

    /**
     * The same decision, with the rule that made it attached.
     *
     * ⛔ **THE REFUSAL IS A VALUE BECAUSE A VALUE IS THE ONLY THING THAT
     * SURVIVES THE ROLLBACK.** `sendText()` writes a short link, an
     * `outreach_messages` row and a credit debit inside one transaction and
     * then throws all three away on four separate conditions. **The rollback is
     * correct and is untouched** — an unsent invite must not leave a spent
     * credit and a row claiming a send, and a committed row would make gate 3
     * refuse this review an invite for ever. What was wrong is that nothing
     * came back out: four rules, one `null`, and no record anywhere that an
     * attempt had been made. ⚠️ **A row written to explain the refusal is
     * inside the transaction being discarded, and a row written outside it is
     * the store of refused attempts decision 10044 refuses in writing.** A
     * returned value stores nothing and is computed before `rollBack()`.
     *
     * ⚠️ **THE CONTROL FLOW IS BYTE-FOR-BYTE WHAT `send()`'s WAS.** The same
     * gates in the same order, the same email-then-SMS ladder, the same mutual
     * exclusion. Only the returned value changed, deliberately: a slice that
     * moved a gate while it was renaming one would be impossible to review.
     *
     * ⚠️ **THE THREE GATES HERE ANSWER {@see InviteAttemptStatus::NotAttempted}
     * AND THAT IS A STATED LIMIT.** Neither the feature switch nor an empty
     * offer is a fact about the recipient, neither opens a transaction, and
     * both are already visible to the tenant on their own screens. The refusals
     * this slice is about are the ones *past* the decision to send. See the
     * enum case for the argument in full.
     */
    public function attempt(
        Review $review,
        Location $location,
        FeedbackPage $page,
        Customer $customer,
    ): InviteAttempt {
        // ⚠️ `=== true`, so anything malformed means do not send. The opposite
        // asymmetry to `impersonation.notify_owner` (706), for the opposite
        // reason: a stray disclosure email to an account holder is noise, and a
        // stray message to somebody else's customer is the one this product
        // cannot take back.
        $emailEnabled = $this->defaults->value('review_invite.email_enabled') === true;
        $smsEnabled = $this->defaults->value('review_invite.sms_enabled') === true;

        if (! $emailEnabled && ! $smsEnabled) {
            return InviteAttempt::notAttempted();
        }

        // ⚠️ Before either permit, because a permit is a record of a decision and
        // minting one we are not going to act on writes a misleading trail.
        $options = $this->invites->offerFor($review, $location, $page);

        if ($options === []) {
            return InviteAttempt::notAttempted();
        }

        // ⚠️ **THE EMAIL ATTEMPT IS CARRIED PAST THE SMS GATE RATHER THAN
        // DISCARDED, WHICH IS NEW AND IS THE POINT.** Where email refused and
        // SMS is switched off or already invited, this used to answer a bare
        // null and the email refusal — the one thing anybody could have acted
        // on — was thrown away between two `if`s.
        $attempt = InviteAttempt::notAttempted();

        if ($emailEnabled && ! $this->alreadySent($review)) {
            $attempt = $this->sendEmail($review, $location, $customer, $options, ReviewInviteKind::Invite);

            if ($attempt->wasSent()) {
                return $attempt;
            }
        }

        // ⚠️ Re-asked after the email attempt rather than alongside it, because
        // the email attempt may have written the very row this asks about — that
        // is what makes the two channels mutually exclusive rather than merely
        // ordered.
        if ($smsEnabled && ! $this->alreadyInvitedOnAnyChannel($review)) {
            return $this->sendText($review, $location, $customer, $options, ReviewInviteKind::Invite);
        }

        return $attempt;
    }

    /**
     * The one follow-up, when nothing came of the invite — T176 P14.
     *
     * ⛔ **THIS IS THE SAME MESSAGE WITH TWO MORE GATES ON IT, WHICH IS WHY IT
     * IS A METHOD HERE RATHER THAN A SECOND SENDER.** Everything the invite
     * carries applies unchanged and unchangeably: the containment, the permit,
     * suppression, the platform opt-out register, the credit debit, the
     * short-link mint, the cost book, and the mutual exclusion between the two
     * channels. A second class would have been a second copy of all of it, and
     * the copy is what drifts.
     *
     * ## The two gates the invite does not have, and why each is here
     *
     * ⛔ **THE SEND-COLLISION ARBITER** (`S1`, T137 `SL-2`). One marketing touch
     * per contact per 24h, priority invite > recovery > reactivation > campaign.
     * The immediate invite does not ask it — it answers a submission the person
     * made seconds ago — but a reminder is chosen by a sweep, on our clock,
     * about a message we sent three days ago. **A reminder that skipped the
     * arbiter would be a second message to somebody the system had just decided
     * not to message**, and neither the recovery path nor a reactivation
     * campaign can see it coming, because there is no lower layer where the
     * three are visible to each other.
     *
     * ⛔ **AND THE LEGAL DAYTIME WINDOW.** T176 §3's R23: conversation
     * *responses* are never hour-gated, but *"the first outbound of a review
     * request or reactivation to a contact rides the legal daytime window"* —
     * 8am–9pm recipient-local under 47 CFR 64.1200(c)(1), stricter in some
     * states. ⚠️ **1618 ruled the immediate invite out of that and the ruling is
     * untouched**: `send()` returns null on a refusal and never retries, so a
     * window there would silently *lose* every evening submission's invite
     * rather than delay it. **The reminder is the case that argument does not
     * reach** — the sweep asks again in fifteen minutes and again tomorrow, so a
     * closed window holds it. See `ConsentService::daytimeWindowRefusal()`,
     * which is `stateRefusal()`'s own body with the purpose gate lifted off, so
     * there is exactly one quiet-hours implementation and this rides it.
     *
     * ⚠️ **`StateUnknown` IS A HOLD AND NOT A PASS**, so a contact with no
     * recorded state gets no reminder. That is 1568's ruling rather than this
     * slice's: a recipient-local window cannot be evaluated without a recipient
     * locale, and federal-only is the *permissive* branch applied exactly where
     * the stricter rule was meant to bind. It is recoverable — fill the contact's
     * state in and the next sweep sends — which is the property that makes
     * refusing affordable here and not in `send()`.
     *
     * ## What "unanswered" means, and what it does not
     *
     * ⚠️ **NO CLICK IS CHECKED. NO REVIEW IS NOT, AND CANNOT BE.** T176 §3 asks
     * for both. `GoogleReviewIngest` writes no `customer_id` and could not —
     * decision 113: no destination platform gives us a completion callback — so
     * a review-side predicate would match nothing and read like a working check
     * (256). **A contact who posted publicly and never opened our link will
     * still be reminded once.** That is the accepted limitation, stated rather
     * than dressed up, and the click check is the strongest proxy available
     * because every destination hand-off goes through our own redirect.
     *
     * ⚠️ **THE CALLER DECIDES THE CHANNEL, AND IT IS THE INVITE'S OWN.** Passing
     * it in rather than re-running `send()`'s email-then-SMS ladder is what
     * keeps a nudge from arriving on a channel the person never heard from us
     * on — and what keeps the reminder from taking the *other* channel when the
     * first one's kill switch is off, which would make an operator's switch read
     * as a channel change.
     *
     * @param  OutreachChannel  $channel  the channel the invite itself went out on
     */
    public function remind(
        Review $review,
        Location $location,
        FeedbackPage $page,
        Customer $customer,
        OutreachChannel $channel,
    ): ?OutreachMessage {
        return $this->attemptReminder($review, $location, $page, $customer, $channel)->message;
    }

    /**
     * The same follow-up, with the rule that refused it attached.
     *
     * ⛔ **TWO OF THIS METHOD'S GATES WERE ALREADY HOLDING A TYPED REASON AND
     * DROPPING IT ON THE FLOOR**, which is the whole of the defect one method
     * up in a smaller form. `ConsentService::decide()` is called here for the
     * consent type, and its `reason` was discarded; `daytimeWindowRefusal()`
     * *returns* a {@see SendRefusalReason} and it was compared against null and
     * thrown away. **Nothing was computed to make this method typed** — the
     * values were already in local variables.
     *
     * @see self::attempt() for why the refusal has to be a value at all.
     *
     * @param  OutreachChannel  $channel  the channel the invite itself went out on
     */
    public function attemptReminder(
        Review $review,
        Location $location,
        FeedbackPage $page,
        Customer $customer,
        OutreachChannel $channel,
    ): InviteAttempt {
        // Gate 1, the feature kill switch, per channel — `send()`'s own, read
        // the same `=== true` way and for the same reason: anything malformed
        // means do not send.
        //
        // ⛔ **`default` RATHER THAN AN EXHAUSTIVE MATCH, AND NEITHER
        // UNHANDLED CHANNEL IS NAMED HERE.** `OutreachChannel` now carries two
        // cases this class does not write — WhatsApp, and (wave 39 lane B)
        // Voice — and `ReviewsTest`'s *"WhatsApp is never offered on an
        // owner-facing surface"* lint allows exactly three files to write that
        // word, none of which is this one. ⚠️ **THIS DOCBLOCK USED TO SAY "THE
        // THIRD CHANNEL" AND "A THIRD VALUE", WRITTEN WHEN WHATSAPP WAS THE
        // ONLY ONE — RE-READ AND LEFT UNCHANGED, WAVE 39 LANE B.** The argument
        // is unaffected by Voice's arrival: the channel here comes off an
        // `outreach_messages` row **this class wrote**, this class still writes
        // only email and SMS rows, and nothing in this lane — or in any lane
        // before an actual voice-calling wave exists — can make it write a
        // third. A value outside {Email, Sms} means the row came from
        // somewhere else, and refusing is both the safe answer and the only
        // honest one, for Voice exactly as it already was for WhatsApp.
        $switch = match ($channel) {
            OutreachChannel::Email => 'review_invite.email_enabled',
            OutreachChannel::Sms => 'review_invite.sms_enabled',
            OutreachChannel::Whatsapp, OutreachChannel::Voice => null,
        };

        if ($switch === null || $this->defaults->value($switch) !== true) {
            return InviteAttempt::notAttempted();
        }

        // ⚠️ **EXACTLY ONE, THEN STOP — AND IT IS ASKED BEFORE ANYTHING ELSE
        // COSTS A QUERY.** `SendInviteReminderJob`'s idempotency key is what
        // holds under a redelivery; this is the ordinary case of two sweeps
        // overlapping, and it is the check that makes the copy's promise
        // ("this is the last message you will get about it") true.
        if ($this->messages->reviewInviteReminderSent($customer)) {
            // ⚠️ **A DUPLICATE RATHER THAN A REFUSAL**, on
            // `SendOutcomeStatus::Duplicate`'s argument: no rule refused this
            // person, an earlier follow-up got there first, and the copy's
            // promise that this is the last message they will get about it is
            // what this arm keeps true.
            return InviteAttempt::duplicate();
        }

        // Gate 2, and before the permit for `send()`'s reason: a permit is a
        // record of a decision, and minting one we will not act on writes a
        // misleading trail. An owner who switched solicitation off in the three
        // days since gets an empty list here and no reminder.
        $options = $this->invites->offerFor($review, $location, $page);

        if ($options === []) {
            return InviteAttempt::notAttempted();
        }

        // ⚠️ **THEY ACTED ON IT — THIS IS THE "UNANSWERED" TEST.** Since the
        // invite left, not ever: a click on some earlier link is not an answer
        // to this message.
        $invitedAt = $this->messages->reviewInviteFor($customer)?->created_at;

        if ($invitedAt === null) {
            // Nothing was ever sent, so there is nothing to follow up. The sweep
            // should not have offered this review, and refusing here rather than
            // trusting it is 398's rule — the outer filter must not be the only
            // thing making this true.
            return InviteAttempt::notAttempted();
        }

        if ($this->clicks->hasCountedClickSince($customer, ShortLinkPurpose::ReviewInvite, $invitedAt)) {
            return InviteAttempt::notAttempted();
        }

        // ⚠️ **`ReviewInvite` IS THE CLASS IT ASKS AS, NOT A CLASS OF ITS OWN.**
        // A reminder is a review invite by another name, so it yields to a real
        // invite inside the window — which is the case that matters, because a
        // resumed deferral can put both in the same day — and gives way to
        // nothing below it. `MarketingTouch::forOutreachPurpose()` classifies the
        // row this writes the same way, so the touch it asks about and the touch
        // it becomes are one class rather than two that could disagree.
        if (! $this->arbiter->allows($customer, $channel, MarketingTouch::ReviewInvite)) {
            // ⚠️ **NO {@see SendRefusalReason} NAMES AN `S1` COLLISION AND THIS
            // SLICE DOES NOT INVENT ONE.** A new case on that enum is a
            // conversation — it needs an `ownerSentence()`, an `isTemporary()`
            // arm and a screen that renders it — and this is a hold rather than
            // a refusal: the sweep asks again tomorrow and the touch window has
            // moved. **Owed and named rather than taken.**
            return InviteAttempt::notAttempted();
        }

        // ⚠️ **THE CONSENT TYPE COMES FROM THE PERMIT'S OWN RECORD, WHICH IS WHY
        // THIS IS ASKED THROUGH `decide()` RATHER THAN GUESSED.** A state that
        // requires prior express *written* consent refuses on the record's
        // strength and not on the hour, and reading that off anything but the
        // record `permit()` would grant is two answers to one question.
        //
        // ⚠️ **THE SEND BODY MINTS ITS OWN PERMIT AGAIN AND THAT IS DELIBERATE,
        // NOT AN OVERSIGHT.** This one is consumed for the consent type only;
        // the permit that actually authorises the message is the one `sendEmail()`
        // or `sendText()` mints inside its own transaction, which is what keeps
        // the invite's settled path unchanged. Asking twice can only ever fail
        // *closed* — a first yes and a second no sends nothing — and the cost is
        // one indexed lookup on a sweep that runs against a bounded candidate
        // set. What it must never become is this permit being *passed down* to
        // the send: a permit minted before the arbiter and the window is a
        // record of a decision made under conditions that have since been
        // re-checked.
        $decision = $this->consent->decide($customer, $channel, OutreachPurpose::Transactional);

        // ⛔ **THE REASON WAS ALREADY IN THIS VARIABLE AND WAS DISCARDED
        // ONE LINE LATER.** `decide()` has answered with it since 391; this arm
        // compared the permit against null and returned a bare null, so a
        // reminder refused because the contact had said STOP was
        // indistinguishable from one refused because a sweep ran twice.
        if (! $decision->isGranted()) {
            return InviteAttempt::refused($decision->reason);
        }

        $windowRefusal = $this->consent->daytimeWindowRefusal($customer, $decision->permit->consentType);

        if ($windowRefusal !== null) {
            // Held, not lost. The sweep asks again on its next pass, and the
            // window has usually moved by then — `SendCollisionArbiter`'s own
            // "it does not defer" rule, which is what keeps a second scheduler
            // from growing here.
            //
            // ⚠️ **THE REASON IS THE ONE THIS METHOD ALREADY RECEIVED.**
            // `daytimeWindowRefusal()` returns a `SendRefusalReason` and this
            // arm compared it against null and threw it away — and the two it
            // can answer are not the same thing at all: `QuietHours` clears by
            // breakfast, `StateUnknown` clears only when somebody fills in the
            // contact's state, and 1620 names that pair explicitly as the one
            // that must not be fused.
            return InviteAttempt::refused($windowRefusal);
        }

        // ⚠️ **TWO BRANCHES AND NO THIRD, BECAUSE GATE 1 ALREADY RETURNED FOR
        // ANY OTHER CHANNEL.** A `match` with a third arm here is not merely
        // redundant — Larastan reports it as unreachable, which is the analyser
        // agreeing that the refusal above is total. The unnamed channel is
        // refused once, at the top, where the reason for not naming it is
        // written down.
        if ($channel === OutreachChannel::Email) {
            return $this->sendEmail($review, $location, $customer, $options, ReviewInviteKind::Reminder);
        }

        return $this->sendText($review, $location, $customer, $options, ReviewInviteKind::Reminder);
    }

    /**
     * The email channel — unchanged by slice 4, and metered since 3299.
     *
     * ⛔ **"NULL NOW HAS ONE MORE CAUSE ON THIS CHANNEL TOO: AN EXHAUSTED
     * BALANCE … DELIBERATELY INDISTINGUISHABLE FROM THEM TO THE CALLER … WHAT
     * TELLS THEM APART IS THE LEDGER, NOT THE RETURN TYPE" — KEPT AND DATED,
     * AND REVERSED** (10190). See `sendText()`'s copy of the same paragraph for
     * the argument in full: they do all mean *no message went*, and they mean
     * four different things to whoever has to fix it — **and the ledger cannot
     * tell them apart, because the debit is inside the transaction that is
     * rolled back.** This method answers an {@see InviteAttempt} now.
     *
     * ⚠️ **THE LAST CLAUSE SURVIVES INTACT AND IS WORTH KEEPING**: the caller
     * falls through to `sendText()` afterwards, which will refuse for the same
     * reason while the two channels share one balance. **Nothing short-circuits
     * on it** — the reason is carried, never acted on, because an email unit and
     * an SMS credit are different quantities against that one balance and
     * "it will refuse too" is a guess this class is not entitled to make.
     *
     * ⚠️ **`$kind` DECIDES THE PURPOSE STRING AND THE COPY AND NOTHING ELSE**
     * (T176 P14). Every gate, the transaction shape, the debit and the cost book
     * are identical for a reminder — deliberately, because they are the parts
     * that must not be re-implemented. The reminder's extra gates are in
     * `remind()`, above this method rather than inside it, so that a reader
     * counting the checks here still finds exactly the set the invite has.
     *
     * ⛔ **AND IT CLAIMS A `SendKey` SINCE 4920, WHICH IS 4813(a) — THE LARGEST
     * THING W12 LEFT OWED.** That lane was scoped to the SMS half and closed it
     * (4803); this channel kept `alreadySent()` as its only protection, **the
     * same check-then-act 350 records as holding only sequentially**, so two
     * workers on one redelivered `SendReviewInviteJob` both read "not yet
     * invited" and both send. The customer-visible failure is a second review
     * invitation to a member of the public, which is the half 4689(b) asks to be
     * judged separately from the cost row — a cost row is a number only we read.
     *
     * @param  list<InviteOption>  $options
     */
    private function sendEmail(
        Review $review,
        Location $location,
        Customer $customer,
        array $options,
        ReviewInviteKind $kind,
    ): InviteAttempt {
        // ⚠️ **THE HALT, THE PAUSE — AND SINCE 6360 THE RATE AS WELL.**
        // `messaging.global_halt` is documented as stopping every channel and a
        // `SendingPause` stops every message for a tenant. This comment used to
        // say *"the rate cannot"* and that the email window did not exist; it
        // does now, written by the send settlement and by the SES delivery,
        // bounce and complaint events, so this line is 2102's automatic trip on
        // this channel too. ⚠️ **It is per message for the reason the class
        // docblock gives**: the complaints arrive *because* the run is running.
        $containment = $this->guard->refusalFor(OutreachChannel::Email);

        if ($containment !== null) {
            // ⚠️ **THE GUARD HAS ANSWERED WITH A `SendRefusalReason` SINCE
            // 2970 AND THIS ARM COMPARED IT AGAINST NULL.** `GlobalHalt`,
            // `TenantPaused` and 2102's automatic complaint-rate trip are three
            // different things an operator does three different things about,
            // and all three left here as one bare null.
            return InviteAttempt::refused($containment);
        }

        // ⛔ **THE TRANSPORT, ASKED HERE RATHER THAN DISCOVERED BY A THROW FROM
        // INSIDE THE TRANSACTION — 10040, 10041.** `PlatformMailer` refuses
        // customer mail on a transport that cannot report a bounce or a
        // complaint, on an unstated 24-hour ceiling, and at the platform-mail
        // reserve. It used to announce all three by throwing
        // {@see \App\Exceptions\MailNotDeliverable} out of `sendToCustomer()`,
        // **which is called from inside the transaction below**, and this method
        // catches only `QueryException` and `CreditMovementRefused` — so the
        // throw walked out of `sendEmail()`, out of `send()`, and into the job.
        // ⛔ **THREE THINGS WERE WRONG WITH THAT AND ONLY THE FIRST WAS EVER
        // WRITTEN DOWN.**
        //
        //   the row     rolled back, which is **correct** and stays correct: no
        //               message went, so an `outreach_messages` row claiming one
        //               did would be a lie, and `alreadySent()` would refuse
        //               this review an invite for the rest of time. That half of
        //               `PlatformMailer`'s own reasoning survives untouched.
        //
        //   the text    ⛔ **`send()` NEVER REACHED `sendText()`.** The whole
        //               point of the ladder above is that SMS is attempted when
        //               email sent nothing; a throw is not "sent nothing", it is
        //               "stopped". So a *mail* misconfiguration silently
        //               cancelled the **text** invite of every tenant with both
        //               channels on — a channel that has no transport, no
        //               ceiling and no feedback signal in common with it.
        //
        //   the retry   the job's three attempts were spent on a **configuration**
        //               refusal that retrying cannot fix, which is the exact
        //               distinction `PlatformMailer::send()`'s docblock draws
        //               about `fail()` versus the backoff ladder — and the row
        //               landed in `failed_jobs`, where 9370–9375 says a
        //               low-volume path is watched by nobody. On a `sync`
        //               deployment it went further still and answered **500 to
        //               the customer's own feedback submission**, measured.
        //
        // ⚠️ **BEFORE THE PERMIT, ON THIS CLASS'S OWN GATE-2 ORDERING**: a permit
        // is a record of a decision and minting one we are not going to act on
        // writes a misleading trail. It is after the containment because the
        // containment is one indexed read and this one counts a rolling window.
        //
        // ⛔ **"NULL IS ALL THE CALLER LEARNS, LIKE EVERY OTHER GATE HERE" WAS
        // TRUE FOR EIGHT DAYS AND IS NOT — KEPT AND DATED** (10190). This arm
        // answers `SendRefusalReason::ChannelUnavailable` and every other gate
        // in this class answers its own rule. ⚠️ **The rest of the paragraph is
        // unchanged and is still the cost**: the run row still reads completed
        // with `invited: false` until `SendReviewInviteJob` asks `attempt()`
        // instead of `send()`, and `SendReviewInviteJob` still spends its claim,
        // so this review is still never re-invited. **A value the caller does
        // not yet read is a smaller gap than no value at all, and it is a gap.**
        //
        // ⚠️ **THE ORIGINAL SENTENCE, VERBATIM**: *"Null is all the caller
        // learns, like every other gate here, and the cost is stated rather than
        // hidden: the run row now reads completed with `invited: false` instead
        // of failed with a transport message on it, and `SendReviewInviteJob`
        // spends its claim, so this review is never re-invited. That is 1618's
        // ruling applied where it already applied — `send()` refuses and never
        // retries — and it trades a `failed_jobs` row nobody reads for a
        // sentence the tenant reads on `Account\Messages`."*
        $mailRefusal = $this->mailer->customerMailRefusal();

        if ($mailRefusal !== null) {
            // ⚠️ **THE TENANT AND THE CHANNEL, AND NOTHING ABOUT THE PERSON.**
            // No customer id, no address: this line fires once per refused
            // invite, which is once per member of the public who left feedback,
            // and 627's warning about `metadata` applies to a log file first.
            // ⛔ **AND IT REACHES NOBODY ON ITS OWN** (9370–9375) — the operator
            // surface for all three of these refusals is `Admin\MailSending`,
            // and the tenant's is the message log. This is for whoever is
            // reading the log after the fact, which is a third audience.
            Log::warning('A review invite was not emailed because this platform may not mail customers right now.', [
                'business_id' => Tenancy::idOrFail(),
                'channel' => OutreachChannel::Email->value,
                'kind' => $kind->value,
                'reason' => $mailRefusal->getMessage(),
            ]);

            // ⚠️ **`ChannelUnavailable` RATHER THAN A CASE OF ITS OWN.** All
            // three conditions behind `customerMailRefusal()` are facts about
            // **our** transport and never about this person — which is exactly
            // what that case is for, and what `SendRefusalReason` already says
            // about `PlatformTexter`'s two nulls one channel over.
            return InviteAttempt::refused(SendRefusalReason::ChannelUnavailable);
        }

        // ⛔ **`decide()` RATHER THAN `permit()`, AND THE DIFFERENCE IS THE
        // WHOLE SLICE.** `permit()` is `return $this->decide(...)->permit;` —
        // one traversal computes a typed reason and the unwrap throws it away.
        // Fifteen of the twenty-one cases on {@see SendRefusalReason} can only
        // ever be produced here, and this path reached none of them: a contact
        // who had said STOP, a contact on a Do Not Call register and a contact
        // with no email address at all were one indistinguishable null.
        // ⚠️ **NOTHING IS COMPUTED TWICE** — `SendDecision`'s own argument for
        // why the pair is safe is that one traversal produces both.
        $decision = $this->consent->decide(
            $customer,
            OutreachChannel::Email,
            OutreachPurpose::Transactional,
        );

        if (! $decision->isGranted()) {
            return InviteAttempt::refused($decision->reason);
        }

        $permit = $decision->permit;

        // ⛔ **THE CLAIM, DERIVED BEFORE THE TRANSACTION OPENS BECAUSE IT CAN
        // THROW** (4920) — `sendText()`'s own rule, and the same helper, so the
        // two channels cannot drift in what they call one occasion.
        $key = $this->inviteSendKey($permit, $review, $kind);

        // ⚠️ THE ROW IS WRITTEN BEFORE THE SEND, INSIDE A TRANSACTION, AND THE
        // ORDER IS THE POINT. `outreach_messages` is the record that we
        // *decided* to send and under which consent record — that is what
        // `29` §2 rule 42 and COMP-01's "consent proof is retrievable" want, and
        // it has to survive the send failing. Writing it afterwards means a
        // delivery nobody can account for whenever the process dies in between.
        //
        // The dispatch sits inside the transaction only because
        // `DeliverPlatformMail` is queued and Laravel's `after_commit` is on for
        // this connection; a rollback takes the job with it.
        //
        // ⚠️ **THE TRY WRAPS THE TRANSACTION RATHER THAN LIVING INSIDE IT**, and
        // that is the difference from `sendText()` one method down. There a
        // refusal has to roll back *without* throwing — the texter answers null
        // for an operator's kill switch — so the transaction is opened by hand.
        // Here the only thing that refuses mid-transaction is the debit, and it
        // refuses by throwing, which `DB::transaction()` already rolls back for
        // us. Opening this one by hand would add two `rollBack()` calls to buy
        // nothing.
        //
        // ⛔ **"THE ONLY THING THAT REFUSES MID-TRANSACTION IS THE DEBIT" WAS
        // FALSE FROM THE DAY IT WAS WRITTEN AND IS TRUE AS OF 10040 — BOTH
        // READINGS KEPT** (4368). `sendToCustomer()` below refused
        // mid-transaction too, on three separate conditions, by throwing
        // {@see \App\Exceptions\MailNotDeliverable} — which neither `catch`
        // names, so it left through this method rather than being handled by
        // it. ⚠️ **The sentence is the reason nobody looked**: a comment that
        // enumerates what can fail is read as a census, and this one was a
        // census with an omission in the direction of reassurance. **The gate
        // above is what made it true**, rather than a wider `catch`, because
        // catching it here would still have paid for the transaction, the mint
        // and the debit before finding out.
        try {
            $message = DB::transaction(function () use ($location, $customer, $permit, $options, $kind, $key): OutreachMessage {
                // ⚠️ **MINTED INSIDE THE TRANSACTION SO A ROLLBACK TAKES THE
                // LINKS WITH IT.** A token minted outside and then orphaned by a
                // failed send is a live tracked link nothing will ever put in a
                // message and nothing will ever revoke. ⚠️ **The refusal below
                // is now one of the things that can orphan one**, which is the
                // same reason 2900 moved the SMS mint inside its transaction.
                $tracked = $this->tracked($options, $customer);

                // ⚠️ **THE ROW AND THE DEBIT ARE ONE OUTCOME** (3299, §3 rail 1's
                // "credit debit is transactional with the send"). The
                // `OutreachMessage::create()` is passed as the closure exactly
                // the way `sendText()` passes its own, so an email that was
                // recorded was charged for and one that was charged for was
                // recorded. The reference is the row the closure returns, whose
                // id does not exist a line earlier (2548).
                //
                // ⚠️ **OUR OWN WRITE ONLY.** `sendToCustomer()` is below and
                // outside the closure — it queues a job rather than opening a
                // socket, but the rule `EmailCredits` states is about the
                // closure's contents and not about how expensive they look.
                /** @var OutreachMessage $message */
                $message = $this->emailCredits->debitForSend(
                    recordTheSend: fn (): OutreachMessage => OutreachMessage::create([
                        'business_id' => Tenancy::idOrFail(),
                        'location_id' => $location->id,
                        'customer_id' => $customer->id,
                        'channel' => OutreachChannel::Email,
                        'purpose' => $kind->outreachPurpose(),
                        // ⚠️ The lane is frozen from the permit, not derived now.
                        // The migration's own docblock: "a historical fact frozen
                        // at send time, unlike customers.messaging_lane which is
                        // derived current state."
                        'lane' => $permit->lane,
                        // ⛔ **THE CLAIM IS THIS INSERT** (4920), exactly as it
                        // is on the SMS path. `send_key` carries a **global**
                        // unique index, so the second attempt at this same send
                        // raises `23505` and is caught outside the transaction
                        // rather than reaching `sendToCustomer()`. It is claimed
                        // by the same INSERT that writes the row and is debited
                        // in the same transaction, which is T137 §3.1's two
                        // halves made one: *"retries and double-clicks can never
                        // duplicate a message or a debit."*
                        'send_key' => $key->value,
                        // ⚠️ NO BODY ON THIS CHANNEL, AND SLICE 4 DID NOT CHANGE
                        // THAT. The column exists and stays null here on purpose:
                        // the message is a rendered template with nothing
                        // tenant-specific in it beyond the business name and links
                        // we can rebuild, and storing a copy of every email is a
                        // second store of customer correspondence with its own
                        // retention question and no reader.
                        // ✅ **The SMS path writes it**, which is what the column
                        // was shaped for — there the body *is* the variable part,
                        // it is not reconstructible from a template, and it is the
                        // only record of the exact words a stranger received.
                        //
                        // ⚠️ **`0` STATED RATHER THAN LEFT NULL, AND ON THIS
                        // CHANNEL IT IS A TYPE-LEVEL FACT** (12461). Media cannot
                        // ride an email at all — `OutboundMessage::for()` throws
                        // on one — so this is not a promise about a code path,
                        // it is the only value the row can honestly take. Null
                        // on that column means *sent before the column existed*,
                        // which a row being written now never is.
                        //
                        // ⚠️ **IT WAS `false` UNTIL 2026-08-30 AND THE COLUMN IS
                        // NOW A COUNT.** Nothing on this path reads it — an
                        // email is debited by `EmailCredits`, which prices a
                        // message and never a photo — so the value is a true
                        // statement about the row rather than an input to a
                        // charge.
                        'media_count' => 0,
                        'status' => OutreachStatus::Queued,
                        'sent_at' => now(),
                        'created_at' => now(),
                    ]),
                    refType: OutreachMessage::CREDIT_REFERENCE_TYPE,
                );

                // ⚠️ **THE BUSINESS NAME AND THE MESSAGE ARE PASSED FOR THE
                // ON-BEHALF-OF IDENTITY AND THE REPLY CODE** (email identity architecture, 2097). The
                // name becomes `"{Business} via GO AI EZ"` in the `From:` display
                // name — never in the address, which stays on the sending domain
                // so SPF and DKIM align — and the message is what the
                // eight-character reply code is threaded back to.
                // `businessName()` rather than `$location->name`, so the identity
                // matches the one `compose()` puts in the SMS.
                $this->mailer->sendToCustomer(
                    $permit,
                    match ($kind) {
                        ReviewInviteKind::Invite => new ReviewInviteEmail($location->name ?? 'the business', $tracked),
                        ReviewInviteKind::Reminder => new ReviewInviteReminderEmail($location->name ?? 'the business', $tracked),
                    },
                    $location->businessName(),
                    $message,
                );

                return $message;
            });
        } catch (QueryException $e) {
            // ⛔ **23505 IS THE `send_key` CLAIM BEING LOST, AND LOSING IT IS THE
            // SUCCESS CASE** (4920) — `sendText()`'s catch, one method down, in
            // the same words. Another attempt at this exact send (same tenant,
            // same channel, same recipient, same review, same kind) already
            // holds the key, so this one must not email the customer a second
            // time. ⚠️ **"Null is this method's whole vocabulary for a gate said
            // no" STOOD HERE AND IS REVERSED** (10190) — and this arm is the one
            // it was least true of, because a lost claim is not a gate saying no
            // at all.
            //
            // ⚠️ **THE TRANSACTION HAS ALREADY ROLLED BACK**, taking the row and
            // every short link the mint created with it — `DB::transaction()`
            // rolls back on the way out and rethrows, so there is nothing to
            // undo by hand. That is the difference from `sendText()`, which
            // opens its transaction manually and must call `rollBack()` itself.
            //
            // ⚠️ **THROUGH `SqlState` RATHER THAN `$e->getCode()`**, which
            // `InboundMessages` documents in terms: that value is an int on some
            // driver paths and a bare string comparison silently misses the
            // match. ⚠️ **ANY OTHER SQLSTATE IS A REAL FAILURE AND IS
            // RETHROWN** — a send that did not happen, reported as a duplicate,
            // is a message silently dropped.
            if (SqlState::of($e) !== '23505') {
                throw $e;
            }

            // ⚠️ **A DUPLICATE, NEVER A REFUSAL.** No rule refused this
            // customer — another attempt at this exact send holds the key —
            // and filing a compliance reason against a message that did go out
            // is `SendOutcomeStatus`'s own argument for keeping the two apart.
            return InviteAttempt::duplicate();
        } catch (CreditMovementRefused) {
            // ⚠️ **THE BALANCE RAN OUT — A REFUSAL, NEVER A HARD FAILURE, AND
            // NEVER A SURPRISE CHARGE.** 2904 and rule 43's surviving half
            // (3294): an exhausted balance degrades and must never throw. This
            // is `sendText()`'s own catch one method down, and
            // `PlatformMessageSender`'s `SendRefusalReason::InsufficientCredit`
            // one class over; this method's whole vocabulary for "a gate said
            // no" is null, so null is what the same event has to mean here.
            //
            // ⚠️ **THE TRANSACTION HAS ALREADY ROLLED BACK BY THE TIME THIS
            // RUNS**, taking the outreach row and every short link the mint
            // created with it — `DB::transaction()` rolls back on the way out
            // and rethrows, which is why there is nothing to undo by hand.
            // Letting the row commit would be gate 3's forever problem in a new
            // costume: `alreadySent()` would refuse this review an invite for
            // the rest of time because the tenant was briefly out of credit.
            //
            // ⛔ **AND NOTHING IS TOPPED UP FROM HERE.** Auto-top-up is opt-in
            // with a tenant-set ceiling and is a CONFIRM action because it
            // spends money (2064, 3306); a send path that quietly bought credit
            // to finish itself is precisely the surprise charge rule 43 forbids.
            //
            // ⛔ **THE SENTENCE ABOVE — *"this method's whole vocabulary for a
            // gate said no is null, so null is what the same event has to mean
            // here"* — WAS THE DEFECT ARGUING FOR ITSELF, AND IT IS KEPT AND
            // DATED** (4368). It was true of the vocabulary and it was never an
            // argument that the vocabulary was right: `PlatformMessageSender`
            // answered `SendRefusalReason::InsufficientCredit` for this exact
            // event one class over, and this path threw the distinction away to
            // stay consistent with itself.
            return InviteAttempt::refused(SendRefusalReason::InsufficientCredit);
        }

        // ⛔ **THE SECOND BOOK, AND IT IS NOT THE DEBIT INSIDE THE TRANSACTION**
        // (3730). That debit is what the *tenant* pays — one email unit, $20 per
        // 1,000 (3299). This is what the send costs **us**, and 3411 recorded its
        // absence as *"3105's trap in a second place"*: a margin figure over a
        // cost nothing books.
        //
        // ⛔ **AFTER THE TRANSACTION HAS COMMITTED, NOT INSIDE IT** (3880–3882).
        // It ran inside, and 2547's rule — a bookkeeping gap must never stop a
        // message — was then held by a nested `DB::transaction()` whose docblock
        // claimed a `SAVEPOINT` contained the failure. It does not contain a
        // deadlock: Laravel rethrows a `DeadlockException` **without** issuing
        // `ROLLBACK TO SAVEPOINT`, so `40P01` aborted this transaction and took
        // the outreach row, the credit debit and the queued mail with it —
        // precisely the failure the guard was added to prevent.
        //
        // ⚠️ **THE ORDERING ARGUMENT THAT WAS ALREADY HERE SURVIVES INTACT.** An
        // exhausted balance throws out of `debitForSend()` and rolls the whole
        // thing back, so a message that was never sent still never books a cost
        // — the `catch` above returns before this line is reached.
        $this->recordCost($message, $kind);

        return InviteAttempt::sent($message);
    }

    /**
     * What this email cost **us**, on the internal book — decision 3730.
     *
     * ⛔ **NOT THE RETAIL CHARGE, AND THE TWO ARE ONE LINE APART IN THE CALLER.**
     * The tenant is charged one email unit ({@see EmailCredits}, $20 per 1,000 —
     * 3299). This is Amazon SES's own charge for the same send, which 3411
     * recorded as *"recorded nowhere, while we sell email at $20 per 1,000"*.
     * Nothing here touches a balance; a tenant who reads their credits sees
     * exactly what they saw before this method existed.
     *
     * ✅ **2976 IS CLOSED ON BOTH CHANNELS, AND THIS PARAGRAPH DESCRIBED IT AS
     * HALF-CLOSED UNTIL 2026-08-18** (2505's shape, six days after 4802 closed
     * the other half). 2976 deferred the cost book on the SMS side because
     * pricing an SMS needed the segment estimate that lived as a private method
     * inside `PlatformMessageSender`, and copying it would put two drifting
     * estimators in a book whose purpose is reconciliation. **The answer was
     * extraction, never a copy** — {@see SmsSegments} (4800), with a lint
     * keeping it singular (4801). **An email has no segments** — it is one email
     * however long it is — which is why that channel never needed the class and
     * was booked first.
     *
     * ⚠️ **NO ROW WHEN NO RATE IS CONFIGURED, AND THE EMAIL STILL GOES** (2547,
     * 3731). Every rate seeds to zero, `costFor()` answers null, and a
     * bookkeeping gap must never stop a message.
     *
     * ⛔ **THIS DOES NOT CONSULT `EmailCredits::UNMETERED_MAILERS`, AND THE DAY
     * THAT ARRAY GAINS AN ENTRY IT MUST** (3742). 3300's exemption is for a
     * mailbox **the tenant connected**, and such a send costs us nothing — so a
     * mailer that is exempt from the retail meter is also a send with no SES
     * charge behind it, and booking one would overstate our own cost. The array
     * is empty and there is no tenant-mailbox send path in `app/` (3402), so the
     * branch has no subject and no test could drive it — and a condition that
     * matches nothing reads like a working feature (256). **The test that
     * asserts the array is empty is what sends the next person here.**
     *
     * ⚠️ **THE IDEMPOTENCY KEY IS THE OUTREACH ROW'S OWN ID**, namespaced by
     * kind and by channel, and it stays that way now that both channels claim a
     * `SendKey` (2975, closed at 4803 and 4920). The row is written inside the
     * caller's transaction and its id is unique by construction, so a retry that
     * reaches here twice with the same row writes once. ⚠️ **Re-keying this on
     * the send key would be a downgrade, not an upgrade**: the send key is the
     * same string across a retry *by design*, so a genuine second charge — a
     * second row, because the claim was released or the first send rolled back —
     * would be swallowed as a duplicate. **A cost row keys on the charge, and a
     * send key keys on the decision to send.** ⚠️ **A retry that re-*sends*
     * creates a new row and books a second cost, which is correct**: SES charged
     * us twice.
     *
     * ⚠️ **"THE ROW IS WRITTEN INSIDE THIS TRANSACTION" IS A CLAIM ABOUT THE
     * QUEUE CONNECTION AS WELL AS ABOUT THIS METHOD** (3884). The caller's own
     * comment says the dispatch sits inside the transaction *"only because
     * `DeliverPlatformMail` is queued and Laravel's `after_commit` is on for this
     * connection"*. On a connection without `after_commit` — `sync` is the one
     * this repository actually uses in tests — the mail job runs **before** the
     * commit, so a rollback can leave a sent email with no outreach row and
     * therefore no cost row keyed to one. That is not a new hazard from this
     * lane and it is not fixed here; it is written down because the idempotency
     * key above is derived from a row whose existence that setting decides.
     *
     * ⛔ **NOTHING IN HERE CAN STOP THE EMAIL, AND ONCE EVERYTHING COULD.**
     * 2547's rule is that a bookkeeping gap must never stop a message, and
     * exactly one bookkeeping failure was handled — "no rate", the null below.
     * Every other one propagated out of the caller's `DB::transaction()` and
     * took the outreach row, the credit debit and the queued mail with it: an
     * operator's typo in `messaging.carrier_cost_currency` (3739's currency
     * validation), a deadlock, or any constraint this table gains later. **The
     * caller's own comment argued the ordering in one direction only** — *"a
     * message that was never sent never books a cost"* — and left the direction
     * 2547 actually rules on unguarded.
     *
     * ⛔ **AND THE FIRST GUARD FOR THAT WAS A SAVEPOINT THAT DOES NOT CONTAIN
     * THE FAILURE IT NAMED** (3880). The body was wrapped in a nested
     * `DB::transaction()` under a docblock claiming Postgres' `SAVEPOINT` made
     * the outer transaction survivable. It does for an ordinary error and **not
     * for a concurrency one**: `ManagesTransactions::handleTransactionException()`
     * sees `causedByConcurrencyError()` with `transactions > 1`, decrements the
     * counter and throws a `DeadlockException` **without issuing `ROLLBACK TO
     * SAVEPOINT`**, leaving the enclosing transaction aborted with `25P02` and
     * the savepoint dangling. Verified against the dev Postgres by raising
     * `22012` (contained) and `40P01` (not contained) through the same shape.
     *
     * ⚠️ **SO THE CONTAINMENT IS THE ORDERING** (3881). The caller now runs this
     * after `DB::transaction()` has returned, at transaction level zero, where a
     * failed statement has nothing open to abort. It no longer depends on the
     * error being classified correctly — which is what a savepoint required, and
     * what `lock_timeout` (`55P03`), a statement timeout and a PHP-level throw
     * would each have got wrong differently.
     *
     * ⚠️ **THE COST ROW IS THE THING GIVEN UP, AND THAT IS THE CHOICE 2547 ALREADY
     * MADE** (3882). A missing row understates our own cost on a book only we
     * read; a thrown exception loses a customer's message. Moving the write after
     * the commit widens that slightly — a process that dies in between loses the
     * row where a rollback would once have lost the whole send — and the
     * `Log::warning` below is what makes it recoverable.
     *
     * ⚠️ **NO CALLER WRAPS `send()` IN ITS OWN TRANSACTION AND ONE DAY ONE
     * MIGHT** (3883). `SendReviewInviteJob` is the only one and opens none. If
     * that changes, the guard is a hand-managed `SAVEPOINT`/`ROLLBACK TO
     * SAVEPOINT` pair — **never** a nested `DB::transaction()`.
     */
    private function recordCost(OutreachMessage $message, ReviewInviteKind $kind): void
    {
        try {
            // ⛔ **THE CHANNEL COMES OFF THE ROW, NEVER FROM THE CALL SITE**
            // (4802). This method was email-only and each of its callers knew
            // its own channel, so a constant worked — and would have been wrong
            // the moment a second channel booked a cost, which is now.
            // `DeliveryReceipts` makes the same argument about the same column
            // in as many words: *"the row already knows what it was."*
            $costKind = match ($message->channel) {
                OutreachChannel::Email => MessageCostKind::OutboundEmail,
                OutreachChannel::Sms => MessageCostKind::OutboundSms,
                OutreachChannel::Whatsapp, OutreachChannel::Voice => null,
            };

            if ($costKind === null) {
                return;
            }

            // ⛔ **SEGMENTS ON SMS AND NULL ON EMAIL, AND THE NULL IS A
            // STATEMENT.** `message_cost_entries.segments` is nullable precisely
            // so "this product is not billed by segment" is sayable; zero would
            // be a claim that an email had none, which is a different and false
            // statement (290's mistake).
            //
            // ⛔ **THE ESTIMATOR IS `SmsSegments` AND THAT IS THE WHOLE OF WHY
            // 2976 COULD BE CLOSED** (4800). It was a private method on
            // `PlatformMessageSender`, and 2976 refused to book this cost
            // because reaching it meant copying it — *"a second copy of a cost
            // estimator is two figures that drift in the one book whose purpose
            // is reconciliation."* Extracting it leaves exactly one, so the two
            // senders cannot disagree about what a three-segment message cost.
            $segments = $costKind === MessageCostKind::OutboundSms
                ? SmsSegments::count((string) $message->body)
                : null;

            $costMillicents = $this->rates->costFor($costKind, $segments);

            if ($costMillicents === null) {
                return;
            }

            $this->costs->record(
                kind: $costKind,
                costMillicents: $costMillicents,
                segments: $segments,
                // ⚠️ **NAMESPACED BY KIND AND BY CHANNEL AS WELL AS BY ROW**
                // (T176 P14, and 4802 for the channel). The two messages are two
                // charges against two different rows, so the row id alone would
                // already be unique — the kind and the channel are in the key so
                // that a reconciler reading the book can tell an invite's charge
                // from its follow-up's, and a text's from an email's, without
                // joining back.
                idempotencyKey: $kind->costKeyPrefix($message->channel).':'.$message->getKey().':'.$costKind->value,
                refType: OutreachMessage::CREDIT_REFERENCE_TYPE,
                refId: (int) $message->getKey(),
            );
        } catch (Throwable $e) {
            // ⚠️ **THE CLASS NAME, NEVER THE PAYLOAD.** This path holds a member
            // of the public's email address, and `CLAUDE.md` keeps personal data
            // out of logs — `ComplianceReplies` states the same rule about a
            // mobile number. The business and the row id are ours and are what an
            // operator needs to find the gap.
            //
            // ⚠️ **AND THE QUERY THAT FINDS IT IS IN `MessageCostLedger`'s
            // DOCBLOCK RATHER THAN LEFT TO BE DERIVED UNDER PRESSURE** (3898).
            // No counter and no metric — there is no metrics facility in this
            // application to hang one on, and a durable counter with one writer
            // and no reader is 272's shape.
            // ⚠️ **THE CHANNEL IS A FIELD RATHER THAN A WORD IN THE SENTENCE**
            // (4802). This line said "A review invite email was sent" while it
            // was the only channel booked; it now covers both, and an operator
            // reconciling a gap needs to know which vendor's statement to open.
            Log::warning('A review invite was sent and its provider cost was not booked.', [
                'reason' => $e::class,
                'business_id' => Tenancy::id(),
                'channel' => $message->channel->value,
                'outreach_message_id' => $message->getKey(),
            ]);
        }
    }

    /**
     * The SMS channel — row 4 slice 4, and the first thing this application ever
     * sends to a handset.
     *
     * ⚠️ **THE PROVIDER MESSAGE ID ONLY EXISTS AFTER THE SEND, AND THE ROW IS
     * WRITTEN BEFORE IT — SO THE ROW IS WRITTEN, THEN SENT, THEN UPDATED, ALL
     * INSIDE ONE TRANSACTION.** That is the honest resolution of two
     * requirements that read as if they conflict. §2.10.1's third property wants
     * the record of the *decision* to exist before the vendor is called, so a
     * process that dies mid-send leaves evidence; slice 3 needs
     * `provider_msg_id` populated or every delivery receipt is unplaceable and
     * the row sits `Queued` forever (1586). Neither is negotiable, so the row
     * lands first at `Queued` with no carrier handle and is completed in place
     * the moment the carrier names one.
     *
     * ⚠️ **A NULL FROM `PlatformTexter` IS A REFUSAL, NOT A FAILURE, AND THE ROW
     * GOES BACK.** ⛔ **"The only thing that returns null there is `sms.enabled`
     * being off" WAS THIS SENTENCE AND IT WAS WRONG FROM DECISION 2550 ONWARD —
     * KEPT AND DATED** (4368, 10191). There are **two**, and that method
     * documents both in as many words: the kill switch, and a null number
     * selection over a **non-empty** inventory — every number quarantined,
     * retired or still registering. ⚠️ **The second is the one that can fire on
     * today's deployment**, where `sms.enabled` is on. ⚠️ **They stay one
     * {@see SendRefusalReason::ChannelUnavailable} on that case's own ruling**,
     * because what `PlatformTexter` distinguishes is behavioural and it settles
     * it internally. Committing the row anyway would record a message that was never sent
     * and never will be — and worse, `alreadyInvitedOnAnyChannel()` would then
     * refuse that customer an invite forever because an operator switched the
     * channel off for an hour. A thrown `TextNotDeliverable` rolls back for the
     * same reason and is re-thrown.
     *
     * ⛔ **THE SENTENCE THAT FOLLOWED — *"`SendReviewInviteJob` hands its claim
     * back only past a successful send, so a retry finds nothing and genuinely
     * re-decides"* — WAS THE WHOLE ARGUMENT AND IT ONLY EVER COVERED HALF THE
     * CASES. KEPT AND DATED, 2026-08-21 (7067).** It is true that the retry is
     * *unblocked*; it says nothing about whether the retry is *safe*, and on a
     * transport failure whose outcome nobody knows — a timeout after Infobip
     * took the message — re-deciding means texting the customer a second time.
     * **The job now hands the claim back only when the transport can prove
     * nothing left the machine.** The rollback here is unchanged and must stay
     * unchanged: nothing is committed and nothing is charged for a send with no
     * outcome.
     *
     * ⛔ **"NULL NOW HAS A THIRD CAUSE: AN EXHAUSTED CREDIT BALANCE" AND THE
     * PARAGRAPH ARGUING THAT THE CAUSES SHOULD BE INDISTINGUISHABLE — KEPT AND
     * DATED, AND REVERSED** (2900, 10190). It read: *"It joins the two above
     * rather than replacing them, and it is deliberately indistinguishable from
     * them **to the caller**, because all three mean the same thing to a review
     * invite — no message went, nothing was charged, and the customer is still
     * invitable when the condition clears. What tells them apart is the ledger,
     * not the return type."*
     *
     * ⛔ **THE FIRST HALF IS TRUE AND THE CONCLUSION DOES NOT FOLLOW.** They do
     * all mean *no message went*; they do not all mean the same thing to
     * anybody trying to fix it. A tenant out of credit tops up, a tenant whose
     * contact said STOP does nothing at all, an operator who quarantined every
     * number releases one, and a paused tenant is resumed by a person. ⚠️ **And
     * the ledger cannot tell them apart either** — four of these five arms
     * write nothing to it, because the debit is inside the transaction that is
     * rolled back. **What tells them apart is now the return type.**
     *
     * ⚠️ **AND A FOURTH: THE CONTAINMENT REFUSED** (2970). The global halt, this
     * tenant's live pause, or 2102's automatic complaint-rate trip firing on this
     * send. ⚠️ **It is the one of the four that refuses before the transaction
     * opens**, so there is nothing to roll back and no short link was ever
     * minted — and it is the one that always had a typed reason in hand, because
     * {@see SendingGuard::refusalFor()} returns one and this method compared it
     * against null.
     *
     * ⛔ **AND THERE ARE FIVE, NOT FOUR — THE `send_key` COLLISION IS NOT ON
     * THIS LIST AND HAS NOT BEEN SINCE 4803.** A second attempt at this exact
     * send raises `23505`, rolls back and returns. It is a **duplicate** rather
     * than a refusal, on `SendOutcomeStatus`'s own argument, which is why
     * {@see InviteAttempt} carries a case for it rather than inventing a
     * `SendRefusalReason`. ⚠️ **A docblock enumerating what can happen is read
     * as a census** — 10048, in this file — so the count is stated here and the
     * arms are the authority.
     *
     * ⚠️ **`$kind` DECIDES THE PURPOSE STRING AND THE WORDING AND NOTHING ELSE**
     * — `sendEmail()`'s note, and the same reasoning. The reminder's extra gates
     * are in `remind()` rather than in here.
     *
     * @param  list<InviteOption>  $options
     */
    private function sendText(
        Review $review,
        Location $location,
        Customer $customer,
        array $options,
        ReviewInviteKind $kind,
    ): InviteAttempt {
        // ⚠️ **BEFORE THE PERMIT AND BEFORE `beginTransaction()`, SO A REFUSAL
        // COSTS ONE QUERY AND NO ROLLBACK** — `PlatformMessageSender`'s step 2.
        // This is the channel 2101 is actually about: every tenant's invites go
        // out over the shared GOAIEZ 10DLC brand from one number pool, so the
        // complaint rate lands on the platform and the automatic trip inside
        // this call is what stops a run partway through.
        $containment = $this->guard->refusalFor(OutreachChannel::Sms);

        if ($containment !== null) {
            // ⚠️ **THE GUARD HAS ANSWERED WITH A `SendRefusalReason` SINCE 2970
            // AND THIS ARM COMPARED IT AGAINST NULL** — `sendEmail()`'s note,
            // and the same three states an operator does three different things
            // about.
            return InviteAttempt::refused($containment);
        }

        // ⛔ **`decide()` RATHER THAN `permit()`.** See `sendEmail()`: the
        // reason is computed by the same traversal that decides the permit and
        // was thrown away by the unwrap. On this channel it is the arm that
        // fires most: `sms.enabled` is on in production and the switch this
        // method's own comment blames below is not what is refusing anybody.
        $decision = $this->consent->decide(
            $customer,
            OutreachChannel::Sms,
            OutreachPurpose::Transactional,
        );

        if (! $decision->isGranted()) {
            return InviteAttempt::refused($decision->reason);
        }

        $permit = $decision->permit;

        // ⛔ **THE IDEMPOTENCY CLAIM, WHICH THIS PATH DID NOT HAVE FOR MONTHS**
        // (2975, 2903, 4689(b); closed at 4803). `PlatformMessageSender` claims
        // a `SendKey` on every send and this class claimed none, so the only
        // thing standing between a retried `SendReviewInviteJob` and a second
        // text to a member of the public was `alreadyInvitedOnAnyChannel()` —
        // **a check-then-act, which decision 350 records as holding only
        // sequentially.** Two workers handling one redelivered job both read
        // "not yet invited" and both send.
        //
        // ⚠️ **DERIVED BEFORE `beginTransaction()` BECAUSE IT CAN THROW**, and
        // a throw here must not leave a transaction open. `SendKey::for()`
        // refuses an identifier that does not normalise — which is not a
        // formatting complaint, it means the permit named an address no gate
        // downstream could act on, and the send was going to fail anyway.
        //
        // ⚠️ **THE OCCASION IS THE REVIEW AND THE KIND, NEVER A CONSTANT** — see
        // {@see self::inviteSendKey()}, which both channels now share (4920).
        $key = $this->inviteSendKey($permit, $review, $kind);

        // Manual rather than `DB::transaction()`, because the refusal below has
        // to roll back and return null — and a closure can only roll back by
        // throwing, which would turn an operator's kill switch into an exception
        // the job records as a failure.
        DB::beginTransaction();

        try {
            // ⚠️ **THE MINT AND THE COMPOSE BOTH MOVED INSIDE THE TRANSACTION,
            // AND THE MINT IS WHY.** Composing used to happen before
            // `beginTransaction()`, which was harmless while it was pure string
            // work. It is not pure any more: the link the body carries is a row,
            // and a token minted outside a transaction that then rolls back is a
            // live tracked link in no message, which nothing will ever revoke.
            $tracked = $this->tracked([$options[0]], $customer);

            $body = $this->compose($location, $tracked[0], $kind);

            /** @var OutreachMessage $message */
            $message = $this->credits->debitForSend(
                // ⚠️ **OUR OWN WRITE ONLY — `SendCredits`' OWN INSTRUCTION.** The
                // carrier call is below and outside this closure. The reference
                // for the debit is this row, whose id does not exist until the
                // closure has run, which is why `debitForSend()` derives it from
                // the return value (2548).
                recordTheSend: fn (): OutreachMessage => OutreachMessage::create([
                    'business_id' => Tenancy::idOrFail(),
                    'location_id' => $location->id,
                    'customer_id' => $customer->id,
                    'channel' => OutreachChannel::Sms,
                    'purpose' => $kind->outreachPurpose(),
                    // Frozen from the permit, exactly as the email path does.
                    'lane' => $permit->lane,
                    // ⛔ **THE CLAIM IS THIS INSERT** (4803). `send_key` carries
                    // a **global** unique index — deliberately not scoped to the
                    // business, per its own migration — so the second attempt
                    // raises `23505` and is caught below rather than sending.
                    // It is claimed by the same INSERT that writes the row and
                    // is debited in the same transaction, which is what makes
                    // T137 §3.1's two halves one half: *"retries and
                    // double-clicks can never duplicate a message or a debit."*
                    'send_key' => $key->value,
                    // ⚠️ **THE COLUMN THIS TABLE WAS SHAPED FOR.** Unlike the
                    // email, this text cannot be rebuilt from a template later —
                    // the destination link, the business name and the lane
                    // disclosure are all resolved at send time, and this row is
                    // the only record of the exact words a stranger received.
                    // `MessageLog` already renders it, truncated, and said "No
                    // message text was recorded" until row 4 slice 4.
                    'body' => $body,
                    // ⚠️ **`false` STATED RATHER THAN LEFT NULL** (9182). A
                    // review-invite text carries no media and this path cannot
                    // compose any — `sendToCustomer()` is called below with no
                    // media argument at all, and `PlatformTexter`'s parameter
                    // defaults to the empty list. So the invite stays **one
                    // credit** under the new rule, exactly as under the old one,
                    // and the row says so rather than leaving the debit to infer
                    // it from a null.
                    // ⛔ **A REVIEW INVITE IS TEXT AND NEVER CARRIES A PHOTO**
                    // (12461, spelled `false` until 2026-08-30). ⚠️ **On this
                    // path the value IS an input to the charge** — unlike the
                    // email site above — so it is stated rather than defaulted,
                    // and the credits this send costs are
                    // `ceil(characters / 160)` off the `body` written beside it.
                    'media_count' => 0,
                    'status' => OutreachStatus::Queued,
                    'created_at' => now(),
                ]),
                refType: OutreachMessage::CREDIT_REFERENCE_TYPE,
            );

            $sent = $this->texter->sendToCustomer($permit, $body);

            if ($sent === null) {
                // ⛔ **`sms.enabled` IS *ONE OF TWO* THINGS THIS NULL MEANS,
                // AND THE COMMENT THAT STOOD HERE NAMED ONLY THE FIRST.** It
                // read *"`sms.enabled` is off. Nothing left, nothing will."*,
                // and this class's own docblock said *"the only thing that
                // returns null there is `sms.enabled` being off"*. **Both were
                // wrong from decision 2550 onward**: {@see PlatformTexter::sendToCustomer()}
                // also returns null when a number was selected as null **and
                // the inventory is not empty** — every number quarantined,
                // retired or still registering — which it documents in as many
                // words. ⚠️ **That is 10048's shape in the same file, one
                // method down**: a comment enumerating what can fail, read as a
                // census, wrong in the direction of reassurance. ⚠️ **And it is
                // the arm that can actually fire today**, because `sms.enabled`
                // is on.
                //
                // ⚠️ **ONE `SendRefusalReason` FOR BOTH, WHICH IS THE ENUM'S
                // OWN RULING AND NOT A SHORTCUT** (2550, restated on
                // `ChannelUnavailable`): the difference `PlatformTexter`
                // protects is *behavioural* — whether the driver may fall back
                // to its configured sender — and it settles that internally.
                // What comes back out is a single null, so a second case here
                // would be this enum claiming a distinction the value it is
                // derived from cannot make.
                //
                // ⛔ **THE ROLLBACK IS UNCHANGED AND MUST STAY UNCHANGED.**
                // Committing the row would record a message that was never sent
                // and never will be, and gate 3 would then refuse this customer
                // an invite for ever because an operator switched the channel
                // off for an hour. ⚠️ **What is new is that the reason is
                // computed before `rollBack()` and returned after it** — a
                // value is the only thing a rollback cannot take.
                DB::rollBack();

                return InviteAttempt::refused(SendRefusalReason::ChannelUnavailable);
            }

            // ⚠️ Slice 3's join key, which of our numbers it left on, and the
            // move to `Sent` — all three through {@see SendSettlement} rather
            // than written here (3030–3033). Without the join key
            // `DeliveryReceipts` matches nothing, the row sits `Queued` forever
            // and `MessageLog` tells the owner "Waiting to send" about a message
            // the customer read yesterday — decision 620's inversion.
            //
            // ⚠️ **AND ACQUIRING THAT KEY IS ALSO THE ONLY HONEST MOMENT TO
            // COUNT A SEND.** `sending_health_windows.sent` is the denominator
            // of `SendingRates::deliveryRateBp()` and had no writer anywhere in
            // `app/` until this slice (2496–2499, 2535, 2977). It moves there
            // because this is exactly when the message joins the population a
            // delivery receipt can land on — the same population the numerator
            // is drawn from. ⛔ **This path counting while the campaign path did
            // not is precisely what 2977 refused**, so neither settles its own
            // row any more, and a lint keeps that method the only one that can.
            $this->settlement->settle($message, $sent->providerMessageId, $sent->numberId);

            DB::commit();

            // ⛔ **THE SECOND BOOK, AFTER THE COMMIT** (4802), on the email
            // path's own ordering and for 3880–3882's reason exactly: a nested
            // `DB::transaction()` does **not** contain a deadlock, so booking
            // this inside would let `40P01` take the outreach row, the credit
            // debit and the text with it. 2547 governs — a bookkeeping gap must
            // never stop a message — and what is given up is that a process
            // dying between the commit and this line loses the row.
            $this->recordCost($message, $kind);

            return InviteAttempt::sent($message);
        } catch (QueryException $e) {
            // ⛔ **23505 IS THE `send_key` CLAIM BEING LOST, AND LOSING IT IS
            // THE SUCCESS CASE** (4803). Another attempt at this exact send —
            // same tenant, same channel, same recipient, same review, same kind
            // — already holds the key, so this one must not text the customer a
            // second time. ⚠️ **"Null is this method's whole vocabulary for a
            // gate said no and is what the caller already handles" STOOD HERE
            // AND IS REVERSED** (10190). `send()` still answers the caller in
            // nulls; `attempt()` answers in {@see InviteAttempt}, and this arm
            // is a **duplicate** rather than a refusal either way.
            //
            // ⚠️ **THROUGH `SqlState` RATHER THAN `$e->getCode()`**, which
            // `InboundMessages` documents in terms: that value is an int on
            // some driver paths and a bare string comparison silently misses
            // the match. ⚠️ **`PlatformMessageSender`'s equivalent catch still
            // uses the bare comparison** — it fails loud rather than silently
            // (it rethrows instead of swallowing) so it is not a duplicate-send
            // hole, but it is named at 4806 rather than left to be rediscovered.
            //
            // ⚠️ **ANY OTHER SQLSTATE IS A REAL FAILURE AND IS RETHROWN.** A
            // send that did not happen, reported as a duplicate, is a message
            // silently dropped — the sibling's own words.
            DB::rollBack();

            if (SqlState::of($e) !== '23505') {
                throw $e;
            }

            // ⚠️ **A DUPLICATE, NEVER A REFUSAL** — `sendEmail()`'s note, and
            // `SendOutcomeStatus`'s argument: nothing refused this person, an
            // earlier attempt holds the key.
            return InviteAttempt::duplicate();
        } catch (CreditMovementRefused) {
            // ⚠️ **THE BALANCE RAN OUT — A REFUSAL, NEVER A HARD FAILURE, AND
            // NEVER A SURPRISE CHARGE.** `29` §2 rule 43: per-tenant cost caps
            // degrade gracefully. This is `PlatformMessageSender`'s own shape one
            // class over — it answers `SendRefusalReason::InsufficientCredit`
            // where it can. ⛔ **AND THE CLAUSE THAT FOLLOWED — *"this method's
            // whole vocabulary for a gate said no is null (the fourth
            // load-bearing property in the class docblock), so null is what the
            // same event has to mean here"* — IS THE DEFECT ARGUING FOR ITSELF,
            // KEPT AND DATED** (4368, 10190). It names the sibling that gets
            // this right in the same breath as discarding what the sibling
            // returns. ⚠️ **The fourth load-bearing property is that a refusal
            // is not an error, and it is untouched** — this still never throws
            // and never reaches the customer. **Nothing in it ever required the
            // refusal to be anonymous.**
            //
            // ⚠️ **THE ROLLBACK TAKES THE ROW, THE MINTED SHORT LINK AND THE
            // DEBIT ITSELF.** Letting the row commit would be gate 3's forever
            // problem in a new costume: `alreadyInvitedOnAnyChannel()` would
            // refuse this customer an invite for the rest of time because the
            // tenant was briefly out of credit. The link goes with it for the
            // reason the mint was moved inside the transaction in the first
            // place.
            //
            // ⛔ **AND NOTHING IS TOPPED UP FROM HERE.** Auto-top-up is opt-in
            // with a tenant-set ceiling and is a CONFIRM action because it
            // spends money (2064); a send path that quietly bought credit to
            // finish itself is precisely the surprise charge rule 43 forbids.
            //
            // ⛔ **AND THE SENTENCE ABOVE — *"this method's whole vocabulary
            // for a gate said no is null, so null is what the same event has to
            // mean here"* — WAS THE DEFECT ARGUING FOR ITSELF.** It cites
            // `PlatformMessageSender`'s `InsufficientCredit` in the same breath
            // as discarding it. Kept and dated (4368); the reason is now what
            // comes back.
            DB::rollBack();

            return InviteAttempt::refused(SendRefusalReason::InsufficientCredit);
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * The same options, with every destination behind a per-send short link.
     *
     * ⛔ **THIS METHOD IS THE THING `ShortLinks::mint()` DID NOT HAVE**, and 2495
     * says so in as many words: *"the review-invite composer and the email engine
     * are the two named consumers and neither calls `ShortLinks::mint()`, so the
     * table has a writer only in tests."* That is decision 272's shape — and the
     * half that bites is the second one, `ShortLinkClicks::countedForCustomer()`
     * having no reader, so a contact's clicks reached no screen.
     *
     * ⚠️ **PER SEND AND PER DESTINATION, WHICH IS WHAT PUTS A CLICK ON A
     * PERSON'S TIMELINE.** 2489's rule: a shared token answers *somebody
     * clicked*, a per-send one answers *this contact clicked*. The customer is
     * passed on every mint, because a click with no `customer_id` is a row
     * `countedForCustomer()` can never find and a timeline that stays empty for
     * a contact who has a history — 1222's exact failure.
     *
     * ⚠️ **THE TARGET IS STILL OUR OWN REDIRECT, NEVER THE PLATFORM'S.** The URL
     * on an `InviteOption` is a `feedback.destination` route (decision 387), and
     * that stays true — the short link points at it, and the platform's real URL
     * is still resolved on the far side by `DestinationSettings::linkFor()`. So
     * there are now two of our own hops rather than one, and the host allowlist
     * remains on the read path where 314–316 put it.
     *
     * ⚠️ **NO EXPIRY, DELIBERATELY, AND IT IS NOT FREE.** A review invite has no
     * deadline: somebody opens the email a month later and should reach the
     * destination rather than a bare 404, which 2490 makes indistinguishable
     * from a token that never existed and would read as the business having
     * gone. What it costs is a tracked link with no end date, so the retention
     * question moves to the prune 2495 already records as unwritten.
     *
     * ⛔ **A MINT THAT FAILS FAILS THE SEND.** There is no fall back to the long
     * URL: on SMS the long URL is what does not fit the 159-unit budget, and a
     * silent downgrade would produce a two-segment message *and* a click nothing
     * records — one send that is wrong in both directions with nothing saying so.
     *
     * @param  list<InviteOption>  $options
     * @return list<InviteOption>
     */
    private function tracked(array $options, Customer $customer): array
    {
        return array_map(
            fn (InviteOption $option): InviteOption => new InviteOption(
                $option->destination,
                $this->links->urlFor($this->links->mint(
                    $option->url,
                    ShortLinkPurpose::ReviewInvite,
                    $customer,
                )),
            ),
            $options,
        );
    }

    /**
     * The one fixed-shape text this slice sends.
     *
     * ⛔ **THIS IS NOT THE COMPOSER AND MUST NOT GROW INTO ONE.** The 140-character
     * composer is doc `43`, which extends `42`, and **`42` was never delivered**
     * (`BUILD-PLAN` §2.10.3 row 7+) — so there is no I1–I8 set for its I9–I18 to
     * sit on. What belongs there and not here: character budgets, variable
     * substitution, per-tenant wording, A/B variants, link shortening, and any
     * notion of a template. This method takes a business name, one URL and a lane
     * and returns one sentence pair. A private method on the sender rather than a
     * class of its own, because a class invites exactly the growth this paragraph
     * refuses.
     *
     * ⚠️ **ONE LINK, THE FIRST OPTION ONLY.** `offerFor()` returns an ordered
     * list — Google first — and an SMS carrying three links is unreadable at a
     * glance, but that is the smaller reason. **Multi-link messages attract
     * carrier filtering**, and a filtered message fails silently with no error
     * path back to us, which is the failure mode this codebase records most
     * often. The email can afford to offer all of them because it has room to
     * label each one; a text cannot.
     *
     * ⚠️ **EVERY LINK IS OUR OWN REDIRECT, NEVER THE PLATFORM'S** — decision 387,
     * unchanged by the channel. The URL comes from `InviteOption`, which builds a
     * `feedback.destination` route; resolving the platform URL on the far side is
     * what keeps `linkFor()`'s host allowlist on every hand-off and what makes the
     * click row exist at all. It is also what keeps the 10DLC campaign's
     * "embedded links: branded domain only, no bit.ly" attribute true.
     *
     * ✅ **AND IT IS NOW A SHORT LINK ON THE OWNED DOMAIN** (2489, 2116). What
     * arrives here is already `https://goaiez.ai/{12 characters}` — thirty
     * units — because {@see self::tracked()} minted it before this method was
     * called. The long form it replaces is a `feedback.destination` route
     * carrying two path segments and a signature, and it is what made the
     * paragraph below true.
     *
     * ⚠️ **THE OPT-OUT SENTENCE IS NOT DECORATION.** Carrier rules require it, and
     * it is the sentence that makes slice 2's whole stop path visible to the
     * person who needs it: `Reply STOP` is honoured by the inbound webhook whether
     * or not a message advertises it, and a recipient who does not know that has a
     * stop path in name only. **HELP is deliberately not advertised here** — it is
     * answered inbound either way, the capture-time disclosure already told them
     * *"Reply STOP to opt out or HELP for help"*, and every character spent here
     * is a character of the message itself.
     *
     * ⛔ **THE DISCLOSURE IS UNCONDITIONAL, AND THIS PARAGRAPH SAID THE OPPOSITE
     * UNTIL 3191.** It read *"the Lane A disclosure is conditional, and a Lane B
     * message must not carry it"*, and the code matched: `MessagingLane::Tenant`
     * got an empty string, on the premise that Lane B rides *the tenant's own*
     * TCR brand and *the tenant's own* number.
     *
     * ⛔ **THE PREMISE IS FALSE AND IT WAS FALSE IN PRODUCTION, NOT IN THEORY.**
     * `phone_numbers` has two writers and `TenantNumbers::assign()` hard-codes
     * `'lane' => MessagingLane::Platform` with 2101's reasoning inline; there is
     * no bring-your-own-number path; and `NumberSelector::forSending()`'s null
     * falls through to the configured sender, which is also ours. **Every
     * reachable sending number is on the GOAIEZ 10DLC brand.** Meanwhile
     * `CapturedBy::Tenant` has a live production writer — `CustomerImports` —
     * so every imported contact carries `MessagingLane::Tenant`, and an imported
     * contact's review invite really did leave our brand with no disclosure.
     * 2192 states the general rule: *"the number belongs to the tenant while the
     * brand stays ours … a `Tenant` lane would assert the tenant holds their own
     * TCR brand, which is the one thing tenant number isolation says they do not."*
     *
     * ⚠️ **TWO AXES WERE CONFLATED.** `MessagingLane` on a permit derives from
     * `CapturedBy` — *who captured the consent* — while this sentence is about
     * *whose brand the number carries*. They are independent, and reading one
     * off the other is what produced an undisclosed message on a shared brand.
     *
     * ⚠️ **`ReactComposer` REACHED THIS CONCLUSION FIRST AND DEFERRED THIS FILE
     * TO ANOTHER LANE** — its `DISCLOSURE` constant is unconditional and its
     * docblock says *"this does not silently change the review-invite path …
     * another lane's file and another lane's ruling to make"*. This is that
     * ruling, made, so all three composers now agree.
     *
     * ⚠️ **IT IS NOT ROUTED THROUGH THE CAPTURE-TIME DISCLOSURE CLASS, AND THAT
     * IS DELIBERATE.** `App\Services\Feedback\ConsentDisclosure` owns the wording
     * a person *agreed to*, and its versions are stored on `consent_records` rows
     * by name — its own docblock: *"never reuse a version for different text."*
     * Putting a message footer in there would mean either bumping a consent
     * version to edit a text message, which invalidates nothing and confuses
     * every stored record, or not bumping it and silently changing what a stored
     * version is understood to have said. Two artefacts, two lifetimes.
     * (Named in prose rather than with `{@see}` on purpose: Pint's
     * `fully_qualified_strict_types` promotes a `{@see}` into a real `use`
     * statement, which is what `SentText`'s footer records — an import that
     * reads as an intent to call something. It did exactly that on this file's
     * first lint run.)
     *
     * ⛔ **NOTHING HERE IS TRUNCATED, AND A LONG BUSINESS NAME MAY STILL PUSH
     * THIS PAST 159 UNITS INTO TWO SEGMENTS.** That is accepted rather than
     * mitigated, and the short link narrows it without closing it: the fixed
     * text plus a thirty-unit link leaves about fifty units for a business name
     * on GSM-7, and a single curly apostrophe anywhere in that name drops the
     * whole message's budget to 70.
     *
     * ⚠️ **AND THIS METHOD STILL DOES NOT MEASURE — `SmsBudget` IS NOT CALLED
     * HERE, ONLY IN `ReactComposer`.** Stated rather than fixed, because the
     * two composers answer differently on purpose: `ReactComposer` **refuses**
     * an over-budget marketing message, which is right when the alternative is
     * a campaign nobody has read; refusing here would silently drop a review
     * invite that a customer has already been promised, over a fraction of a
     * cent. The measurement belongs with a wording the tenant can shorten, and
     * this method's wording is fixed.
     * Decision 1570 refused truncation in the transport — *"a helpful
     * `substr($body, 0, 160)` is the 140-character composer built by accident, in
     * the layer least able to say what a message may say"* — and the argument is
     * stronger here, because the first thing a length limit would cut is the tail,
     * and the tail is the opt-out instruction and the lane disclosure. **A
     * two-segment message costs a fraction of a cent; a truncated compliance
     * disclosure is a compliance failure that reports as a successful send.**
     * Fitting this into one segment is the composer's problem to solve later,
     * with a character budget it is allowed to have.
     *
     * ⚠️ **NO INCENTIVE, NO OFFER, NO PROMOTIONAL LANGUAGE.** `24` §3.3 permits a
     * review request *because* it stays strictly transactional, and
     * `OutreachPurpose`'s own docblock warns that nothing in the enum can notice
     * when a composer breaks that. This method is the boundary.
     *
     * ⚠️ **THE FOOTER IS NOW READ FROM `legal.sms_ask_footer` RATHER THAN
     * WRITTEN HERE, AND THE BYTES ARE UNCHANGED** (CC-4, decision 5172). R53's
     * legal registry is *"versioned · zero-deploy · rendered wherever
     * referenced"*, and the review-ask footer is the string CC-5 composes onto
     * every ask template — so it needed one name rather than a literal in each
     * composer. The seed is byte-equal to the sentence this line used to hold.
     *
     * ⛔ **AND THE OBJECTION `MissedCallTextBack::compose()` RAISES IS ANSWERED
     * RATHER THAN OVERRULED.** That file refuses a registry key in as many
     * words — *"a wording an owner can edit is a wording an owner can edit the
     * disclosure out of"* — so `DefaultsRegistry::set()` consults
     * `LegalCanon::refuseIncompleteAskFooter()` and will not save a value that
     * drops the lane disclosure or the opt-out instruction. **The missed-call
     * composer is deliberately not moved**: it is another lane's settled file
     * and this brief is the review ask.
     *
     * ⚠️ **THE TWO NOW HOLD THE SAME WORDS BY TWO MECHANISMS, WHICH IS A NEW
     * DIVERGENCE AND NOT 3182's.** 3182 recorded the composers disagreeing
     * about the *wording* — this one was lane-conditional and the missed-call
     * one was not — and 3191 closed that by making both unconditional. What
     * differs now is *where the words live*, and `LegalTest`'s "every composer
     * that discloses the platform says what the footer canon says" pins the
     * seed against both remaining literals so the sentence cannot drift apart
     * in silence.
     */
    private function compose(Location $location, InviteOption $option, ReviewInviteKind $kind): string
    {
        $business = trim($location->businessName());

        if ($business === '') {
            // A text that names nobody is both a carrier-filtering problem and a
            // compliance one — `24` §3.2 has the business as the sender of
            // record. `businessName()` already falls back to the location's own
            // name; this is the case where neither is set at all.
            $business = 'the business';
        }

        // ⚠️ **THE REMINDER IS THE SHORTER OF THE TWO, AND THAT IS NOT A STYLE
        // CHOICE** (T176 P14). Every unit spent here comes out of the same
        // 159-unit budget the invite already overruns for a long business name,
        // and this message carries one extra obligation the invite does not: it
        // has to say it is the last one. **The disclosure and the opt-out
        // sentence are untouched and unconditional** — 3191's ruling, and the
        // tail is the first thing any length pressure would cut.
        //
        // ⚠️ **NO INCENTIVE, NO OFFER, NO URGENCY.** `24` §3.3 permits a review
        // request *because* it stays strictly transactional, and a second
        // message is where "last chance" arrives. There is no scarcity language
        // here and there must never be: it would make this marketing
        // retroactively, for every send, and `29` §2's first rule bans the
        // incentive outright regardless of classification.
        $ask = match ($kind) {
            ReviewInviteKind::Invite => "thanks for your feedback. If you have a moment, post it publicly: {$option->url}",
            ReviewInviteKind::Reminder => "your feedback link is still open if you would like to post it: {$option->url}\nThis is our last note about it.",
        };

        return "{$business}: {$ask}\n"
            .$this->defaults->string(LegalCanon::ASK_FOOTER_KEY);
    }

    /**
     * The idempotency claim for one review invite, on either channel — 4920.
     *
     * ⛔ **ONE METHOD BECAUSE THE OCCASION STRING IS THE THING THAT DRIFTS, AND
     * W12's OWN FINDING IS THE ARGUMENT.** 4800 extracted {@see SmsSegments}
     * rather than copying it because *"a second copy of a cost estimator is two
     * figures that drift in the one book whose purpose is reconciliation"*, and
     * the same reasoning reaches further here: a second copy of an occasion is
     * two keys that drift in the one mechanism whose purpose is *not sending
     * twice*. A copy that gained `:email` on one side and not the other would
     * still deduplicate each channel perfectly and would silently stop being one
     * rule — and nothing would fail, because both halves would look right.
     *
     * ⚠️ **THE CHANNEL IS DELIBERATELY NOT IN THE OCCASION, AND LEAVING IT OUT
     * IS THE LOAD-BEARING PART.** {@see SendKey::for()} hashes the permit's own
     * **identifier** into the key — a mobile number on one channel and an email
     * address on the other — so an email permit and an SMS permit for the same
     * review produce two different keys from this one string. **Adding the
     * channel here would be harmless today and wrong in principle**: it would
     * invite the next reader to believe the occasion is what separates the
     * channels, which is the belief that makes removing it look safe.
     *
     * ⚠️ **`SendKey::for()` ALSO HASHES THE CHANNEL, AND THAT IS BELT-AND-BRACES
     * RATHER THAN THE MECHANISM** — driven by mutation on 2026-08-18: removing
     * it alone leaves every test green, because the two identifiers already
     * differ. Said plainly so nobody mistakes the redundant guard for the real
     * one, which is 398's rule turned around.
     *
     * ⚠️ **AND THE TWO CHANNELS ARE MUTUALLY EXCLUSIVE ABOVE THIS ANYWAY**
     * (1604, 1605) — `alreadyInvitedOnAnyChannel()` is what stops one person
     * getting both. This key is the layer beneath that, which holds when the
     * check-then-act does not.
     *
     * ⚠️ **THE OCCASION IS THE REVIEW AND THE KIND, NEVER A CONSTANT.** The
     * invite and its one follow-up are two legitimately different messages to
     * the same person about the same review; a shared occasion would collapse
     * them into one key and **the reminder would be dropped as a duplicate with
     * no error anywhere** — `SendKey`'s own docblock spells that failure out.
     *
     * ⚠️ **IT CAN THROW, WHICH IS WHY BOTH CALLERS DERIVE IT BEFORE THEY OPEN A
     * TRANSACTION.** `SendKey::for()` refuses an identifier that does not
     * normalise — not a formatting complaint, it means the permit named an
     * address no gate downstream could act on and the send was going to fail
     * anyway.
     */
    private function inviteSendKey(SendPermit $permit, Review $review, ReviewInviteKind $kind): SendKey
    {
        return SendKey::for($permit, 'review_invite:'.$review->getKey().':'.$kind->value);
    }

    /**
     * Whether an email invite has already gone to this customer.
     *
     * ⚠️ **THE NAME OF THIS METHOD AND ITS QUERY DISAGREE, AND SLICE 4
     * DELIBERATELY DID NOT RESOLVE IT** (1606). The docblock said *"one invite
     * per review, ever"* while the query has always been keyed on
     * `customer_id` + purpose + channel — so what it actually enforces is **one
     * email invite per customer, ever**, across every review they ever leave. The
     * two readings differ for any returning customer, and which one is right is a
     * product question rather than a code one. It is reported rather than changed
     * because changing it either way alters what the settled email path does, and
     * this slice's brief was to add a channel and not to move the email one. The
     * sentence above now describes the query.
     *
     * ⚠️ **Best-effort under concurrency, and stated rather than implied** —
     * decision 350's shape exactly. This is a SELECT then an INSERT with no
     * unique index behind it, so two genuinely simultaneous dispatches for one
     * review can both pass. `SendReviewInviteJob`'s idempotency key is the layer
     * that actually holds under a retry; this one catches the ordinary case of
     * the same review being routed twice, and closing it properly needs a
     * partial unique index this slice does not have room to add.
     */
    private function alreadySent(Review $review): bool
    {
        return OutreachMessage::query()
            ->where('customer_id', $review->customer_id)
            ->where('purpose', 'review_request')
            ->where('channel', OutreachChannel::Email)
            ->exists();
    }

    /**
     * Whether this customer has already been invited on **any** channel.
     *
     * ⚠️ **THE CHANNEL FILTER IS DROPPED ON PURPOSE, AND IT IS THE ONLY GATE IN
     * THIS CLASS THAT DIFFERS BETWEEN THE TWO CHANNELS** (1605). Copying
     * `alreadySent()` and swapping `Email` for `Sms` is the obvious move and it
     * is wrong: it would give a customer one invite *per channel*, so somebody
     * who left one review would get an email and then a text about it. **Double-
     * contacting the same person about the same thing is the failure this
     * product exists not to commit**, and it is the one a recipient reads as
     * spam regardless of how well-consented both messages were.
     *
     * Asymmetric with `alreadySent()` above rather than both being widened,
     * because widening the email side changes what the settled channel does and
     * that is a separate ruling (1606). The asymmetry is safe in this direction
     * and only in this direction: email is attempted first (1604), so an email
     * invite is always visible to this check by the time it runs, while the
     * reverse ordering would let an email follow a text.
     *
     * Best-effort under concurrency for `alreadySent()`'s reason, and covered by
     * the same idempotency key.
     */
    private function alreadyInvitedOnAnyChannel(Review $review): bool
    {
        return OutreachMessage::query()
            ->where('customer_id', $review->customer_id)
            ->where('purpose', 'review_request')
            ->exists();
    }
}
