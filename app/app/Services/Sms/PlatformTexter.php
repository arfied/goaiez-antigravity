<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\ReachesRecipients;
use App\Contracts\Texter;
use App\Enums\OutreachChannel;
use App\Enums\OwnerNotificationKind;
use App\Exceptions\TextNotDeliverable;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentService;
use App\Services\Consent\OwnerConsentService;
use App\Services\Consent\OwnerSendPermit;
use App\Services\Consent\SendPermit;
use App\Services\Mail\PlatformMailer;
use App\Services\Ops\OperatorAlerts;
use App\Support\Tenancy;
use LogicException;

/**
 * The one place this application sends a text message.
 *
 * ⚠️ **THE PERMIT IS A REQUIRED PARAMETER AND THAT IS THE WHOLE MECHANISM**
 * (285, restated for this channel as 1566). {@see ConsentService} is the only
 * thing that can produce a {@see SendPermit} — private constructor, one factory,
 * an `ArchitectureTest` lint confining `grant()` to `app/Services/Consent/` — so
 * a caller who has not been through the consent gate cannot call this method at
 * all. Not *"will fail a check"*: **cannot construct the argument.**
 *
 * ⚠️ **THE TEMPTING SHAPE IS `send($customer, $body)` WITH THE CONSENT LOOKUP
 * INSIDE, AND 1566 EXISTS BECAUSE IT READS BETTER AT EVERY CALL SITE.** It is
 * also the version the second caller bypasses, because nothing in the signature
 * says a decision was ever made. The asymmetry is the point: a missing permit
 * must be impossible to express, not merely wrong. Same reasoning that put
 * `gating_ack_at` in front of the thresholds rather than beside them — ⚠️ that
 * column was removed by the owner's ruling at decision 2074 and no longer exists
 * to grep for, but the shape it demonstrated is why this signature looks like
 * this.
 *
 * ⚠️ **THERE IS NO UNPERMITTED `send()` HERE, AND {@see PlatformMailer} HAS
 * ONE — AND THAT SENTENCE USED TO CLAIM THERE WAS NO ACCOUNT-HOLDER CATEGORY
 * ON SMS AT ALL, WHICH WAS TRUE UNTIL THE OWNER'S RULING OF 2026-08-27
 * (10540).** It read: *"an owner who gets a text from us is being texted at a
 * number that reached us through a consent record like anyone else's… there
 * is no such category on SMS."* {@see self::sendToOwner()} is that category,
 * built once the owner was asked directly whether an account holder's own
 * mobile number needs a recorded basis and answered **yes** — express
 * consent, captured in the wizard, stored as a `consent_records`-shaped row
 * like any other. ⚠️ **WHAT THE OLD PARAGRAPH GOT RIGHT SURVIVES INTACT**:
 * this is still not `PlatformMailer::send()`'s shortcut, because it still
 * requires a permit — {@see OwnerSendPermit}, minted only by
 * {@see OwnerConsentService} from a recorded consent
 * event, never from the bare account relationship `PlatformMailer::send()`
 * relies on. The two account-holder sends on the two channels now use
 * *different* authorisation models on purpose: mail's is the account
 * relationship, SMS's is a consent record, because SMS is the channel with a
 * regulator and mail's own docblock says so.
 *
 * ⚠️ **AND THAT PARAGRAPH STANDS UNCHANGED WITH {@see self::alertOperator()}
 * BESIDE IT** (P23, 2026-08-15). That method texts **the platform's own
 * operator** at a number they typed into the registry themselves — not an
 * account holder, not a customer, and with no recipient parameter for a caller
 * to put one in. It is not the `send()` refused above with a different name, and
 * the difference is written out in its own docblock rather than assumed here.
 * ⚠️ **NOR DOES {@see self::sendToOwner()} COLLAPSE INTO IT.** `alertOperator()`
 * pages one of *our own* staff at a number typed into the platform registry,
 * with no recipient parameter and no consent record because the platform is
 * nobody's customer; `sendToOwner()` pages a *tenant's* account holder, at a
 * number they consented to be texted at, and takes a permit for exactly that
 * reason.
 *
 * ⚠️ **IT SENDS TO `$permit->identifier`, NEVER TO A NUMBER READ OFF THE
 * MODEL.** The identifier on the permit is the one suppression, the Do Not Call
 * and litigator registers, the reassigned-numbers check and the mini-TCPA
 * windows were all decided against. A sender that re-reads `$customer->phone`
 * can read a *different* number — a record edited between the permit and the
 * send, or simply a second number — and every gate upstream would have been
 * asked about somebody else.
 *
 * ## Two gates, independently falsifiable (391)
 *
 * **The channel is checked, because a permit is per channel.** An email permit
 * type-checks here perfectly and authorises nothing about SMS: consent is per
 * `(customer, channel)` and `24` §3.4's registers answer differently for each.
 * This is the one thing the type system cannot say on its own, so it is said
 * here — and it is checked **first**, before the kill switch, so that handing
 * this method the wrong permit is loud even while the channel is switched off.
 * Checking it second would make the mistake invisible for as long as
 * `sms.enabled` is false, which was every day until slice 2 shipped and is every
 * day again the moment an operator turns it off.
 *
 * **The kill switch, `sms.enabled`.** ✅ **Seeded true since slice 2** (1585),
 * because the thing it waited for now exists: *you may not send what you cannot
 * stop*, and inbound STOP handling is built. It seeded **false** through slice 1
 * for that reason, which was sequence rather than symmetry with the email
 * switch.
 *
 * ⚠️ **IT BEING TRUE IS NOT "SMS IS LIVE", AND READING IT THAT WAY IS THE
 * MISTAKE.** `SMS_DRIVER` still seeds `log`, the inbound webhook refuses
 * everything until its signing key is set, and the 10DLC campaign was filed
 * and REJECTED (11617). What this switch now is, is the fastest way to stop every outbound
 * text without a deploy.
 *
 * ⚠️ **REFUSAL IS SILENT AND RETURNS NULL** — `ReviewInviteSender`'s fourth
 * load-bearing property (`BUILD-PLAN` §2.10.1). Null is not an error and never
 * reaches the customer.
 *
 * ⚠️ **THIS DOES NOT WRITE `outreach_messages` AND MUST NOT LEARN TO.** The row
 * is written *before* the send, inside a transaction, by the sender that owns
 * the decision — §2.10.1's third property, and the reason the email path puts it
 * in `ReviewInviteSender` rather than in `PlatformMailer`. A transport that files
 * its own record would file it after the fact, which loses every send the
 * process dies in the middle of.
 *
 * ## The sending number is chosen here, and never by the transport
 *
 * ⚠️ **THIS CLASS CALLS {@see NumberSelector}, AND `InfobipClient` MUST NEVER
 * LEARN TO.** Which number a message leaves on is a decision — about inventory,
 * about a tenant's own Lane B number versus the shared Lane A pool, and
 * eventually about health — and {@see Texter}'s own docblock forbids the
 * transport learning anything decision-shaped. The transport is handed a string.
 *
 * ⚠️ **A NUMBER THAT MAY NOT SEND REFUSES SILENTLY, LIKE THE KILL SWITCH.** If
 * every number available to this send is quarantined, retired or still
 * registering, this returns null — the same silent refusal, for the same reason:
 * null is not an error and never reaches the customer. What it must never do is
 * fall through to the configured sender, which would make a quarantine
 * unenforceable while every test stayed green.
 */
final class PlatformTexter
{
    public function __construct(
        private readonly Texter $texter,
        private readonly DefaultsRegistry $defaults,
        private readonly NumberSelector $numbers,
        /**
         * ⚠️ **LAST, AND DEFAULTED, WHICH IS THE HONEST SHAPE HERE** —
         * `App\Services\Sms\InboundMessages`' own convention for a
         * dependency-free collaborator. {@see OwnerNotifications} takes no
         * constructor arguments and reaches nothing but its own table, so a
         * default costs the container nothing and leaves every existing
         * three-argument construction of this class — six of them in
         * `tests/Feature/Messaging/PlatformTexterTest.php` alone — untouched
         * by a slice that has no business editing them.
         */
        private readonly OwnerNotifications $notifications = new OwnerNotifications,
    ) {}

    /**
     * Send to a tenant's customer, which needs authorisation this class cannot
     * mint.
     *
     * Returns what the carrier said, or **null when the kill switch is off**.
     *
     * @param  string  $body  Finished text. Not a template and not truncated —
     *                        {@see Texter} says why the composer is not here.
     * @param  list<string>  $mediaUrls  MMS attachments, as URLs a carrier can
     *                                   fetch. Empty is a plain SMS.
     *                                   ⚠️ **THE MEDIA CHANGES NOTHING ABOVE
     *                                   THIS LINE AND THAT IS THE POINT** (2544):
     *                                   the permit gate, the channel check, the
     *                                   kill switch and the number selection are
     *                                   identical, because MMS is the same
     *                                   consent, the same number and the same
     *                                   carrier reputation as the SMS it rides
     *                                   with. Only the transport branches.
     *
     * @throws LogicException when handed a permit for another channel, or an
     *                        empty body
     * @throws TextNotDeliverable when the transport could not send
     */
    public function sendToCustomer(SendPermit $permit, string $body, array $mediaUrls = []): ?SentText
    {
        if ($permit->channel !== OutreachChannel::Sms) {
            throw new LogicException(
                "A {$permit->channel->value} permit does not authorise a text message. Consent is per "
                .'channel, and so are the Do Not Call and mini-TCPA registers behind it.'
            );
        }

        if (trim($body) === '') {
            // A programming error rather than a refusal, so it is loud. An empty
            // SMS still costs a segment, still arrives, and still counts against
            // the brand's daily throughput — the recipient just cannot tell what
            // it was for.
            throw new LogicException('A text message with no body is not a message. Compose it first.');
        }

        // ⚠️ `!== true`, so anything malformed means do not send —
        // `ReviewInviteSender`'s asymmetry, for its reason: a stray message to
        // somebody else's customer is the one this product cannot take back.
        if ($this->defaults->value('sms.enabled') !== true) {
            return null;
        }

        $number = $this->numbers->forSending();

        if ($number === null && $this->numbers->hasNumbers()) {
            // ⚠️ **THE TWO NULLS ARE NOT THE SAME NULL, AND COLLAPSING THEM IS
            // THE DEFECT THIS BRANCH EXISTS TO PREVENT.** A null selection with
            // an empty inventory is the bootstrap case — no numbers recorded
            // yet, and the driver falls back to its configured sender, which is
            // what every environment did before this slice. A null selection
            // with numbers on the table means every one of them is quarantined,
            // retired or still registering, and that has to stop the send.
            //
            // Silent, on `ReviewInviteSender`'s fourth property: this is an
            // operational state somebody's own health rules produced, not a
            // failure, and the caller rolls its row back rather than recording a
            // message that will never go.
            return null;
        }

        $sent = $this->texter->send(
            to: $permit->identifier,
            body: $body,
            reference: $this->reference(),
            from: $number?->e164,
            // Passed straight through. ⚠️ **NO `array_values()` HERE, AND THAT
            // IS THE ANALYSER'S FINDING RATHER THAN A PREFERENCE**:
            // `OutboundMessage::for()` already normalises the list, so a second
            // call is a claim that this layer does not trust the value object —
            // and a transport that re-derives its arguments is one commit from
            // re-deriving the number.
            mediaUrls: $mediaUrls,
        );

        // ⚠️ FILLED IN AFTER THE TRANSPORT ANSWERS, FROM THE SELECTION MADE
        // ABOVE — never re-derived from what the carrier echoed back. The
        // carrier is not asked which of our numbers it used, and a value read
        // back off a vendor response is a vendor's claim about our inventory.
        return $sent->withNumber($number?->id);
    }

    /**
     * Send to a business's own account holder, about their own account
     * (10540, the owner ruling of 2026-08-27).
     *
     * ⛔ **NOT `sendToCustomer()` WITH A DIFFERENT ARGUMENT.** The permit type
     * is {@see OwnerSendPermit}, never {@see SendPermit} — see that class's
     * own docblock for why the two must not collapse into one. There is no
     * channel check here the way `sendToCustomer()` opens with one: an
     * `OwnerSendPermit` cannot be minted for any channel but SMS, so the
     * check that method needs to tell an email permit from an SMS one has
     * nothing to distinguish here and would be dead code asserting a fact the
     * type already guarantees.
     *
     * ⚠️ **NO `outreach_messages` ROW, FOR `replyToInbound()`'s AND
     * `alertOperator()`'s REASON.** That table is the customer-outreach
     * ledger — `SendSettlement` reconciles it against `SendPermit`-gated sends
     * — and an owner-channel message is neither a campaign nor a delivery a
     * tenant needs reconciled against their own customer list. Nothing here
     * debits a tenant's SMS credit grant either — **decision 10546 is the
     * ruling and this is where it is enforced**: an owner-channel send is a
     * platform operating cost rather than a draw on the 500 messages a tenant
     * bought to text their own customers, on `PlatformMailer::send()`'s
     * existing unmetered account-holder mail as the precedent.
     * ⚠️ **THE PROVENANCE HERE WAS WRONG UNTIL WAVE 40 (10970).** It sourced
     * the rule to `App\Jobs\EscalateUrgentThreadJob`'s docblock, *"made at the
     * one call site that exists"* — and **a job's docblock is not the authority
     * for a platform rule**, which is the whole of the correction. ⛔ **The
     * claim itself is unchanged and must not be softened**; what moved is where
     * a reader is sent.
     * ⛔ **AND 10970's HEADLINE ADDED *"AND THERE IS NO LONGER ONE CALL SITE"*,
     * WHICH IS FALSE — CORRECTED 2026-08-28 (wave 41 lane D, 11083).** That
     * clause stood in this paragraph too. **There is exactly one call site in
     * `app/`** — `App\Jobs\EscalateUrgentThreadJob`'s urgent escalation —
     * confirmed two ways on this tree: a whole-tree grep whose other sixteen
     * occurrences are prose and tests, and `php artisan ops:method-callers
     * --suspect`, which resolves calls rather than matching text and scores
     * this declaration **`1 call site(s)`**. ⚠️ **The correction 10970 makes is
     * right and its premise is not, and the premise is what a reader
     * believes** — this file is where somebody comes to check. ✅ **The durable
     * argument for not sourcing a platform rule to a call site is that it is a
     * platform rule**, which holds at one call site and at ten; *"there are
     * several now"* is an argument that expires the moment somebody counts. ⚠️ **The alternative — debiting `SendCredits` like a
     * customer-facing send — is equally defensible and is the OWNER's to
     * choose**, and `app/Services/Billing/TrialReminders.php` argued the
     * opposite in prose for a fortnight (10940). ✅ **What reddens if this
     * stops being true is `tests/Feature/Billing/OwnerChannelGrantTest.php`**,
     * which drives a funded tenant through this method and asserts neither
     * pool moves — and drives a **zero**-balance tenant through it to prove
     * the door does not refuse, because a debit here would silence the urgent
     * page for exactly the tenant least able to absorb it.
     *
     * ✅ **AND SINCE WAVE 40 LANE A IT LEAVES A DURABLE RECORD, WHICH IS A
     * DIFFERENT QUESTION FROM THE ONE THE PARAGRAPH ABOVE ANSWERS** (10820).
     * *No `outreach_messages` row* is about **receipts and reconciliation** —
     * `SendSettlement` walks that ledger against `SendPermit`-gated sends, and
     * an owner-channel message is not one. It says nothing about whether the
     * send should be recorded **anywhere**, and until 10820 it was not: no send
     * row, no occasion, no `sent_at`, so after an owner-directed text left this
     * platform nothing in the database said it had. {@see OwnerNotifications}
     * writes `owner_notifications` instead — a separate, tenant-owned table
     * that leaves the reconciler's population exactly as it was.
     *
     * ⛔ **THE KIND AND THE OCCASION ARE REQUIRED PARAMETERS FOR 1566's OWN
     * REASON.** A sender that cannot say what it is texting about cannot call
     * this method, so *"an owner-directed send is recorded"* is a property of
     * the signature rather than of whoever remembered to write a line after
     * it — which is what the next sender, the lifecycle-ladder SMS rungs
     * `CLAUDE.md` names as owed, would otherwise have had to remember.
     * ⚠️ **The recorder is called structurally; the ROW is best-effort** —
     * see {@see OwnerNotifications::record()} for why a swallowed insert is
     * the only safe failure once a carrier has already taken the message.
     *
     * ⚠️ **NUMBER SELECTION IS UNCHANGED FROM `sendToCustomer()`** — the
     * tenant's own Lane B number first, the shared Lane A pool second, the
     * same two nulls with the same two meanings. A quarantined inventory
     * refuses an owner-channel send exactly as it refuses a customer one;
     * there is no separate reputation to protect.
     *
     * @param  OwnerNotificationKind  $kind  What this text is about, for the
     *                                       record `owner_notifications`
     *                                       keeps of it. Required — see above.
     * @param  string  $occasion  The event it is about, in the SENDER's own
     *                            idempotency vocabulary rather than one derived
     *                            here: for the escalation that is the carrier's
     *                            id for the inbound message that matched, so a
     *                            redelivered webhook and a retried job name one
     *                            occasion instead of two.
     * @return SentText|null null when the kill switch is off or no number may
     *                       send — `sendToCustomer()`'s same silent refusal,
     *                       for the same reason. ⚠️ **And nothing is recorded
     *                       on either refusal**, which is correct: no message
     *                       left.
     *
     * ⛔ **AND SINCE 11300 IT REFUSES A TRANSPORT THAT REACHES NOBODY, WHICH
     * IS A THROW AND NOT ONE OF THE TWO NULLS ABOVE.** See
     * {@see self::assertTransportReaches()} for the measurement: the mail half
     * of this page has refused a `log` transport structurally since it was
     * written, the SMS half answered `true` unconditionally, and the union of
     * the two is what decides whether a member of the public is told the
     * business has been called.
     *
     * @throws LogicException on an empty body
     * @throws TextNotDeliverable when the transport could not send, and when
     *                            the bound driver cannot send at all
     */
    public function sendToOwner(
        OwnerSendPermit $permit,
        string $body,
        OwnerNotificationKind $kind,
        string $occasion,
    ): ?SentText {
        if (trim($body) === '') {
            throw new LogicException('A text message with no body is not a message. Compose it first.');
        }

        if ($this->defaults->value('sms.enabled') !== true) {
            return null;
        }

        $this->assertTransportReaches();

        $number = $this->numbers->forSending();

        if ($number === null && $this->numbers->hasNumbers()) {
            // sendToCustomer()'s own comment: every number quarantined,
            // retired or still registering must stop the send rather than
            // fall through to a configured sender.
            return null;
        }

        $sent = $this->texter->send(
            to: $permit->identifier,
            body: $body,
            // ⚠️ NULL, AND NOT `(string) $permit->businessId` — `replyToInbound()`'s
            // and `alertOperator()`'s reason exactly. `reference()` below
            // exists to carry a delivery receipt home to an `outreach_messages`
            // row, and this method writes none, so there is nothing for a
            // receipt to be applied to whether or not it names a business.
            reference: null,
            from: $number?->e164,
        );

        // ⛔ **AFTER THE TRANSPORT ANSWERS AND NEVER BEFORE IT.** A row written
        // first would claim a send a transport failure then never made, which
        // is `operator_alerts.emailed_at`'s inversion — a record of a dispatch
        // read as a record of a delivery. `SentText::$providerMessageId` does
        // not exist until a carrier has taken the message, so ordering it this
        // way is what makes "a row exists" mean something.
        $this->notifications->record(
            businessId: $permit->businessId,
            kind: $kind,
            occasion: $occasion,
            providerMessageId: $sent->providerMessageId,
        );

        return $sent;
    }

    /**
     * Answer an inbound message on the number it arrived at, with no permit.
     *
     * ⛔ **READ THE `send()` PARAGRAPH IN THIS CLASS'S DOCBLOCK BEFORE THIS
     * METHOD, AND THEN READ WHY IT DOES NOT APPLY.** That paragraph refuses an
     * unpermitted send on SMS and the refusal stands for **outreach** — anything
     * this platform originates to somebody it chose to contact. Consent is the
     * condition for that, always.
     *
     * This is not outreach. It is a **carrier-mandated auto-response to an
     * inbound message, sent back to the number that just messaged us**, carrying
     * no offer and no invitation. 2099 is explicit that *"STOP, HELP and
     * suppression are unconditional under both"* sending bases — and
     * **unconditional means it must not consult a consent record**, because
     * consulting one denies an answer to precisely the person most likely to ask:
     * somebody with no record who wants to know who is texting them. 2125 and a
     * registered 10DLC campaign both require it to work.
     *
     * ## What stops this becoming the shortcut the class docblock warns about
     *
     * ⚠️ **THREE THINGS, AND THE FIRST IS THE ONLY ONE THAT REALLY MATTERS.**
     *
     *   1. **The caller cannot choose who to message.** `$to` is the sender of
     *      an inbound message the carrier just handed us. There is no path from
     *      a customer list to this method — a campaign would have to receive a
     *      message from every recipient first, which is the definition of not
     *      being outreach.
     *   2. **The caller cannot choose the words.** {@see ComplianceReplies}
     *      composes the only sentence it can compose, from the tenant's own
     *      record, with no body parameter of its own. This method takes a string
     *      because it must, and the thing that supplies it has no way to vary it.
     *   3. **A lint confines the callers** to the compliance-reply path, the
     *      same way the consent lane confines who may mint a permit at all.
     *      ⚠️ **That sentence originally named the permit factory method
     *      literally and failed the build**, because `ConsentTest`'s minting
     *      lint reads `getContents()` rather than stripped source — so a
     *      *comment* quoting the call is an offender. The lint is right to be
     *      strict and was not touched; the prose moved.
     *
     * ⚠️ **THE KILL SWITCH IS CHECKED AND THE COMPLAINT TRIP IS NOT.** A paused
     * tenant still answers HELP — refusing to say who you are *because* too many
     * people have complained is backwards, and it is when the answer matters
     * most. But an operator who halts SMS has genuinely halted it; that trade,
     * and its compliance consequence, are argued in {@see ComplianceReplies}.
     *
     * @return SentText|null null when the channel is off or no number may send —
     *                       the same silent refusal `sendToCustomer()` makes, for
     *                       the same reason.
     *
     * @throws LogicException on an empty body
     * @throws TextNotDeliverable when the transport could not send
     */
    public function replyToInbound(string $to, string $body): ?SentText
    {
        if (trim($body) === '') {
            throw new LogicException('A compliance reply with no body tells the recipient nothing.');
        }

        if ($this->defaults->value('sms.enabled') !== true) {
            return null;
        }

        // ⛔ **`forComplianceReply()`, NOT `forSending()`, AND THE DIFFERENCE IS
        // A BLOCKER THAT SHIPPED** (AG6 fix wave). This path used the outreach
        // selector, so the day the first auto-quarantine fired on the shared
        // Lane A number, **every STOP confirmation and every HELP answer on the
        // platform stopped with it** — and `InboundMessages::stop()` writes the
        // suppression before calling here and swallows the failure, so the
        // opt-out was honoured while the person was told nothing. 2099 and 2125
        // make both replies unconditional; the health state is not one of the
        // two things this path defers to. See the selector for the admitted set.
        $e164 = $this->numbers->forComplianceReply();

        if ($e164 === null && $this->numbers->hasNumbers()) {
            // Unchanged in shape and much narrower in reach: the only way to
            // land here now is an inventory in which every number is retired,
            // released or not yet registered — none of which we may text from.
            return null;
        }

        $sent = $this->texter->send(
            to: $to,
            body: $body,
            // ⚠️ **`id()` RATHER THAN `idOrFail()`, AND THAT IS THE ONE
            // DIFFERENCE FROM `sendToCustomer()` HERE.** A HELP arriving on the
            // shared Lane A pool number resolves to no tenant at all, and the
            // reply is the platform answering for itself — there is no business
            // id to send, and demanding one would throw inside a carrier webhook
            // and leave the carrier's HELP test unanswered. `sendToCustomer()`
            // keeps `idOrFail()` because it is only reachable past a permit and a
            // permit only exists inside a tenant.
            //
            // ⚠️ **NOTHING IS LOST BY SENDING NULL.** The reference exists to
            // carry a delivery receipt home to an `outreach_messages` row, and a
            // compliance reply writes none — so the receipt has nothing to be
            // applied to whether or not it names a tenant.
            reference: Tenancy::id() === null ? null : (string) Tenancy::id(),
            from: $e164,
        );

        // ⚠️ **NO `withNumber()` HERE, AND NOTHING IS LOST.** That id exists to
        // tie a send to its `outreach_messages` row, and a compliance reply
        // writes none — `SendSettlement` states it. The selector returns the
        // E.164 alone for the reason its own docblock gives: a resting number is
        // not a `SendingNumber` and must never be handed out as one.
        return $sent;
    }

    /**
     * Text the platform's own operator — P23's bell, and the third
     * authorisation model in this application.
     *
     * ⛔ **READ THIS CLASS'S "THERE IS NO `send()` HERE" PARAGRAPH FIRST, AND
     * THEN READ WHY IT DOES NOT REACH THIS METHOD.** That paragraph refuses an
     * unpermitted send to an **account holder** — a business owner — and it is
     * right: an owner's number reached us through a consent record like anybody
     * else's, `24` makes consent the condition for texting a person, and
     * "because mail has one" is not an argument on the channel with a regulator.
     *
     * **This recipient is not an account holder and not a customer. It is us.**
     * One number, typed into the platform registry by the operator whose phone
     * it is, to page themselves when their own machinery breaks. There is no
     * consent record to consult because `consent_records` is keyed on
     * `customer_id` and the platform is nobody's customer, and there is no
     * tenant on whose behalf this goes out.
     *
     * ## Three things stop this becoming the shortcut, and the first is the one
     * that matters
     *
     *   1. **THE CALLER CANNOT CHOOSE WHO TO MESSAGE — THERE IS NO RECIPIENT
     *      PARAMETER.** The number is read here, from
     *      {@see OperatorAlerts::SMS_KEY}, and a caller holding a customer's
     *      phone number has nowhere to put it. This is `replyToInbound()`'s
     *      first property, made stronger: that one at least takes a `$to`.
     *   2. **The words are a rendered alert summary**, and the one caller
     *      composes them from counts, rates and thresholds — never from customer
     *      text, and never from a vendor's error string.
     *   3. **A lint confines the callers** to {@see OperatorAlerts}, exactly as
     *      one confines `replyToInbound()` to `ComplianceReplies`.
     *
     * ⚠️ **IT IGNORES `sms.enabled` AND `messaging.global_halt`, AND THAT IS THE
     * DELIBERATE PART.** Those switches stop **outbound messaging to other
     * people** — that is what an operator throwing one is asking for, and
     * `replyToInbound()` honours `sms.enabled` for that reason. Neither of them
     * is a request to stop being paged; the moment sending is halted is the
     * moment an operator most needs to hear from their own platform, and a
     * halt-aware pager would go quiet in exactly the incident it was bought for.
     * **The off switch is the number: blank means no texts.**
     *
     * ⚠️ **THE COST OF IGNORING THEM IS BOUNDED BY THE CALLER, NOT BY THIS
     * METHOD**, which is worth knowing before adding a second caller.
     * `OperatorAlerts` fires at most one text per alert **kind and subject** per
     * quiet window; without that de-duplication a stuck condition would text a
     * real phone every five minutes forever, and 511's failure would arrive as a
     * person blocking the sender.
     *
     * ⛔ **AND THAT SENTENCE SAID "PER ALERT KIND" UNTIL 2026-08-22, WHICH IS THE
     * WHOLE OF THE DEFECT IT WAS RELIED ON TO EXCLUDE — CORRECTED (7820–7839).**
     * The dedupe key is `(kind, subject)`, and **four kinds take a tenant id as
     * their subject** — two of them raised on the unauthenticated pixel
     * collector, whose public key sits in the page source of every tenant's own
     * website. So *"at most one text per kind"* read as a fixed ceiling and was
     * in fact *one text per tenant somebody chose to name*, unbounded in the one
     * variable an outsider controls. **A paragraph asserting a bound that the
     * mechanism does not provide is what stops the next reader checking**
     * (314–316), and it is why this correction is longer than the fix.
     *
     * ✅ **THE CALLER NOW BOUNDS IT IN THE DIMENSION THAT WAS OPEN.**
     * `OperatorAlerts::PUSH_BUDGET_PER_KIND` caps how many times a day one kind
     * may reach either push channel **when an inbound HTTP request is what rang
     * it**; a bell this platform's own clock raised is never bounded, never
     * queued and never withheld — which is deliberate and is the constraint that
     * shaped the remedy, because a pager that can be dropped in an incident is
     * worse than the flood. ⚠️ **Nothing about *this* method changed**, and the
     * paragraph above still stands: the switches stay ignored, and the off switch
     * is still the number.
     *
     * ⛔ **AND SINCE 11460 IT REFUSES A TRANSPORT THAT REACHES NOBODY, WHICH IS
     * `sendToOwner()`'s GUARD ON THE CHANNEL WHERE THE ANSWER IS WRITTEN DOWN.**
     * {@see OperatorAlerts::text()} stamps `operator_alerts.texted_at` from
     * whatever this method returns, and on the shipped `SMS_DRIVER=log` default
     * that was a fabricated `SentText` — so the one column this platform reads
     * to answer *did anybody hear the bell* said yes for a text nobody
     * received. 11305 called this the reporting failure of the four unguarded
     * sends and `replyToInbound()` the compliance one; **read against the tree
     * the order is the other way round**, and the reason is that this is the
     * only one of the four whose false answer is **written into the database
     * and read back by an instrument built to answer exactly that question**.
     * See {@see self::assertTransportReaches()} for the allowlist and for which
     * two paths are still deliberately unguarded.
     *
     * @return SentText|null null when no operator number is configured, or when
     *                       no number in the inventory may originate traffic.
     *
     * @throws LogicException on an empty body
     * @throws TextNotDeliverable when the transport could not send, and when the
     *                            bound driver cannot send at all
     */
    public function alertOperator(string $body): ?SentText
    {
        if (trim($body) === '') {
            throw new LogicException('An operator alert with no body tells nobody anything.');
        }

        $to = trim((string) $this->defaults->stringOrNull(OperatorAlerts::SMS_KEY));

        if ($to === '') {
            return null;
        }

        // ⛔ **AFTER THE BLANK ROW AND BEFORE EVERYTHING ELSE, BECAUSE THE TWO
        // STATES IT SEPARATES LOOKED IDENTICAL FROM HERE** (11460). A blank row
        // is the operator's own off switch — the paragraph above says so — and
        // it stays a silent null, or a platform nobody asked to be paged on
        // reports a fault. A **configured** number on a transport with no
        // carrier behind it is the other thing entirely, and until this line it
        // returned a fabricated `SentText` that {@see OperatorAlerts::text()}
        // stamped into `texted_at`. That column is this platform's own answer
        // to *did anybody hear the bell*, so the omission was 9371's
        // `emailed_at` defect reproduced on the sibling column 9371 held up as
        // the honest one.
        //
        // ⚠️ **A THROW AND NOT A THIRD NULL, WHICH IS MOST OF WHY IT IS HERE.**
        // Both refusals around it return null, and a null is what
        // {@see \App\Services\Ops\ChannelProbeResult::refused()} reports — *"configured and
        // declined before the transport"*, which is the quarantined-inventory
        // state and needs a different remedy. A throw becomes
        // {@see \App\Services\Ops\ChannelProbeResult::failed()} carrying
        // {@see \App\Exceptions\TextNotDeliverable::transport()}'s message,
        // which names the `SMS_DRIVER` value an operator would change.
        $this->assertTransportReaches();

        // ⚠️ **`forComplianceReply()` RATHER THAN `forSending()`, AND FOR THE
        // SAME REASON THAT CALL IS THERE.** An operator alert must survive the
        // state where every number is quarantined — that state is *itself* one
        // of the things worth being paged about, and a pager silenced by the
        // incident it is reporting is the AG6 defect wearing a different hat.
        $e164 = $this->numbers->forComplianceReply();

        if ($e164 === null && $this->numbers->hasNumbers()) {
            // Every number is retired, released or unregistered — none of which
            // this platform may text from. Silent, like every other refusal
            // here; the alert has already been logged and emailed by the caller.
            return null;
        }

        return $this->texter->send(
            to: $to,
            body: $body,
            // ⚠️ **NULL, AND NOT `Tenancy::id()`.** A delivery receipt exists to
            // find an `outreach_messages` row and this send writes none — but
            // more than that, this message belongs to no tenant at all, and
            // stamping whichever tenant happened to be established when the
            // sweep noticed would tell the carrier a business sent it.
            reference: null,
            from: $e164,
        );
    }

    /**
     * Refuse to pretend, on the channel where the answer is read as a fact.
     *
     * ⛔ **`PlatformMailer::assertDeliverable()`'s TWIN, AND IT IS HERE BECAUSE
     * ITS ABSENCE WAS A DEFECT** (11300). `EscalateUrgentThreadJob::page()`
     * takes the union of the two channels — `$paged = $mailed || $texted` — and
     * that union decides three things: whether the job rethrows and is retried,
     * whether its idempotency claim is spent for ever, and **which of two
     * sentences a member of the public who texted *"there is a gas leak"*
     * receives.** The mail half refuses a `log` or `array` transport by
     * throwing; the SMS half had no equivalent, because
     * `LogTexter::send()` answers every call with a
     * `SentText` and its own docblock says *"IT NEVER FAILS"*. **The union of a
     * real answer and a constant `true` is `true`**, so the mail half's
     * structural refusal was silently defeated by the channel wave 42 added
     * beside it. Driven on `SMS_DRIVER=log` with `mail.default=array`: run row
     * `succeeded`, `paged: true`, `mailed: false`, `texted: true`, the claim
     * still held, an `owner_notifications` row naming a `log-<uuid>` handle,
     * and the customer told *"I have passed this straight to Ledger Heating as
     * urgent … so a person will read it and reply to you here."*
     *
     * ⛔ **AN ALLOWLIST, NOT A DENYLIST OF DRIVER NAMES.**
     * `PlatformMailer::UNDELIVERABLE` is a list of transports that cannot
     * deliver, and mirroring it here reads best on a diff and is 511's shape: a
     * second source of truth beside `AppServiceProvider::smsDriver()`'s `match`
     * that fails **open** on the next driver nobody adds to it. A transport
     * declares {@see ReachesRecipients} or it is treated as reaching nobody.
     *
     * ⛔ **IT COVERS TWO OF THE FOUR SENDS AND THAT SENTENCE SAID "THE OWNER
     * CHANNEL" — CORRECTED 2026-08-28 (11460).** {@see self::alertOperator()}
     * now calls it too. **The superseded text is kept and dated on 4368's
     * rule** because its refusal of the other two is unchanged and is the part
     * a reader needs: *"⛔ **AND IT IS SCOPED TO THE OWNER CHANNEL
     * DELIBERATELY.** {@see self::sendToCustomer()} is not repaired and must not
     * be repaired here: the log driver exists so the customer-outreach ledger,
     * the STOP handling, the delivery receipts and the number-health slices can
     * be built without a carrier (`BUILD-PLAN` §2.10.4), and a refusal there
     * would delete the seam rather than fix it. {@see self::replyToInbound()}
     * and {@see self::alertOperator()} are the other two unrepaired paths and
     * their answers are read differently again — raised at 11305, not acted
     * on."*
     *
     * ⛔ **`sendToCustomer()` STAYS UNGUARDED AND THE ARGUMENT ABOVE IS WHY**
     * (11463). It writes an `outreach_messages` row and `SendSettlement` moves
     * it to `Sent`, which is a record claiming a delivery — so the question was
     * put again with that fact in hand and answered the same way: on a `log`
     * install that row **is** the seam §2.10.4 describes, refusing there stops
     * every row-4 slice being testable without a carrier, and the tenant
     * reading `/account/messages` on such an install does not exist.
     *
     * ⛔ **`replyToInbound()` STAYS UNGUARDED FOR A DIFFERENT REASON, AND IT IS
     * THE ONE WORTH CARRYING** (11462). Nothing there records anything: no
     * `outreach_messages` row, no `SendingHealth` count, no audit row, no feed
     * item, and `inbound_messages` has no `replied_at` column, so the discarded
     * boolean is the whole of the claim. `InboundMessages::stop()` writes the
     * suppression **first and unconditionally** and answers second, so a dead
     * transport is *"we failed to confirm"* and never *"we failed to suppress"*.
     * A guard there would buy one log line on an install with no carrier
     * delivering inbound to it — **the population is empty by construction** —
     * and would cost the seam sentence above its STOP clause.
     *
     * ⛔ **AND ON THE RUNNING INSTALL THE POPULATION IS EMPTY FOR A SECOND AND
     * STRONGER REASON, ESTABLISHED BY THE OWNER READING THE BOX RATHER THAN BY
     * ANYTHING DERIVABLE HERE** (11483). `credentials.infobip_webhook_secret`
     * is **not set**, so `InfobipWebhookVerifier::verify()` fails closed and
     * `InfobipInboundController` answers **401 to every genuine delivery**:
     * `InboundMessages::handle()` is never reached, and this method is
     * therefore not merely sending nothing — **it is never called**.
     * ⛔ **The thing that costs is upstream of it and is not this class's**:
     * the suppression write the paragraph above relies on being first is on the
     * far side of that 401 too, so a customer texting STOP today is **not
     * suppressed at all**. **The ordering only helps once the request gets past
     * the signature check**, and saying otherwise here would be a guard
     * described as covering a case it never sees.
     *
     * ⚠️ **A THROW RATHER THAN THIS METHOD'S NEIGHBOURING SILENT NULLS**, and
     * the two above it are why: `sms.enabled` being off and every number being
     * quarantined are both an operator's deliberate stop, and this is a
     * deployment that cannot send at all. `PlatformMailer` answers the same
     * question by throwing, `EscalateUrgentThreadJob` catches
     * it into a warning naming the account, and the two halves of the union now
     * answer a non-delivering transport the same way — which was the whole
     * defect.
     *
     * @throws TextNotDeliverable
     */
    private function assertTransportReaches(): void
    {
        if ($this->texter instanceof ReachesRecipients) {
            return;
        }

        // ⚠️ **THE CONFIGURED NAME AND NEVER THE CLASS.** An operator changes
        // `SMS_DRIVER`; the class name is the accurate answer to a question
        // nobody is asking.
        $driver = config('services.sms.driver');

        throw TextNotDeliverable::transport(is_scalar($driver) ? (string) $driver : gettype($driver));
    }

    /**
     * The opaque string the carrier hands back on the delivery receipt.
     *
     * ⚠️ **THIS DOCBLOCK WAS ORPHANED BETWEEN 11300 AND 11460 AND IS PUT BACK.**
     * It was spliced apart from its signature when `assertTransportReaches()`
     * and its own docblock landed **between** the two, so PHP attached the
     * later block to that method and this one documented nothing — while
     * `reference()`, the thing it is about, had none. **It is not incidental
     * prose**: it is the written decision record for a deliberate disclosure of
     * a tenant id to a third party, with the rejected alternative and the
     * *"never trusted on the way back"* argument.
     *
     * ⚠️ **THE TWO SIBLINGS THIS NAMED ARE REPAIRED, AND THERE WERE FOURTEEN**
     * (wave 45). This said a sibling predated the wave in `InfobipClient` and a
     * third sat in `InboundMessages`. Both are fixed — ⛔ **and `InboundMessages`
     * held TWO, not one**: `start()`'s lift-not-delete argument was stranded by
     * its own `@param` block in the same commit that stranded `stop()`'s, and
     * two waves looked straight at that file without seeing it. ⛔ **The
     * population was never three. `DocblockTest` counts it, and eleven orphans
     * are still awaiting their file's owner.** The line numbers this sentence
     * carried are deliberately not replaced with new ones.
     *
     * ⚠️ **IT IS THE BUSINESS ID, AND THAT IS A DELIBERATE DISCLOSURE TO A
     * THIRD PARTY RATHER THAN AN INCIDENTAL ONE.** A delivery receipt arrives
     * with no tenant — same as an inbound STOP — but unlike a STOP it has to
     * find *one specific row* in `outreach_messages`, which is tenant-owned and
     * RLS-`FORCE`d. With no tenant the query matches nothing, so without
     * something carrying the tenant home the receipt cannot be applied at all
     * and every row sits `Queued` forever, which is the exact failure
     * `BUILD-PLAN` §2.10.3 says this slice exists to prevent.
     *
     * WHAT IS ACTUALLY SENT: an integer of ours. No name, no phone number,
     * nothing about a person — and the vendor already holds the recipient's
     * mobile number, which is the sensitive half. `29` §2.4 treats the tenant
     * id as safe to record; sending it is a step further and is recorded as a
     * decision rather than slipped in.
     *
     * **The alternative was a platform-scoped table mapping the carrier's
     * message id to a business**, written on every send. It keeps the id off
     * the vendor entirely and costs a table plus a write per message. It was
     * not taken because the vendor supplies this field for exactly this
     * purpose, and a second store of send metadata is a second thing that can
     * disagree with `outreach_messages` about what was sent.
     *
     * ⚠️ **AND IT IS NEVER TRUSTED ON THE WAY BACK.** {@see DeliveryReceipts}
     * uses it to *establish* a tenant and then looks the message up by the
     * carrier's own id — so RLS decides whether that row is really theirs. A
     * forged or stale reference finds nothing and is dropped. The reference
     * selects which tenant's rows are visible; it never asserts that a row
     * belongs to anybody.
     */
    private function reference(): string
    {
        // idOrFail rather than id: this method is only reached past the permit,
        // and a permit exists only inside a tenant. A null here would silently
        // send a message whose receipt could never be applied.
        return (string) Tenancy::idOrFail();
    }
}
