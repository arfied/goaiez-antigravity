<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Contracts\MessageSender;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\SendRefusalReason;
use App\Models\Customer;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SendPermit;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendKey;
use App\Services\Messaging\Outbound\SendOutcome;
use App\Support\Identifier;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * SM-001 — the text a caller gets when the business did not pick up.
 *
 * ⛔ **DECISION 3112 FOUND THIS MISSING, AND THE FINDING WAS CORRECT.** Verified
 * again on 2026-08-13 before a line was written: `AutopilotActionType::CallMissed`
 * carried the feed sentence *"Texted back a missed call"*, `CallForwarding` and
 * `TenantNumbers` explained in four docblocks why the tenant's own number has to
 * be the one the text leaves from, `VoiceEventType::owesTextBack()` answered the
 * question and had a test — and **nothing anywhere sent the text**. Of the three
 * channels 2066 puts on one shared SMS balance, this is the second to exist.
 *
 * ## The consent question is open, and this class refuses rather than answers it
 *
 * ⛔ **THERE IS NO RULING IN `docs/DECISIONS.md` THAT AN INBOUND CALL IS A
 * SENDING BASIS.** The neighbouring rulings were all read. 27 permits *inbound
 * answering* because "the customer initiated an inbound call" — it is about
 * **calls**, not texts, and about answering rather than originating. 2098–2102
 * add exactly one basis beside platform-captured consent and it is
 * `ImportAttestation`, a tenant attesting about a list. 2103 describes the missed-call
 * chain and stops at *"the SM-001 text-back to the caller"* without saying what
 * authorises it. **None of those reaches a stranger who dialled a number.**
 *
 * So this class does the only thing it may: it asks {@see ConsentService} for a
 * `Transactional` permit exactly as every other sender does, and **a caller with
 * no consent record is refused with `NoConsentRecord`.** ⚠️ **That is the common
 * case and very likely the universal one today** — a first-time caller has no
 * `Customer` row, let alone a consent record — so this sender is close to inert
 * in production until the owner rules. That is deliberate, and it is 3102's
 * shape rather than a defect: a send path that refuses is recoverable, and a
 * consent record manufactured from a phone call is not.
 *
 * ⛔ **NOTHING HERE WRITES A `ConsentRecord`, AND NOTHING HERE MAY.** The
 * tempting one-liner — *they called us, so record express consent* — would make
 * `messaging_lane` a lie (`29` §2: derived and unsettable; 2099 restates it),
 * would file a platform-captured consent artefact for a disclosure nobody was
 * ever shown, and would defeat every gate above it in one line. **A basis is a
 * ruling, not a judgement call**, and the question is recorded as owner-blocking
 * rather than answered here.
 *
 * ## Two gates a compliance review found missing on the first pass (3179, 3180)
 *
 * ⛔ **THE CALL MUST OWE A TEXT-BACK.** `29` §19.6 makes *"answered call
 * produces no text-back"* build-failing, and the first version of this class
 * accepted any {@see InboundCall} and said *"sorry we missed your call"*
 * unconditionally — while `VoiceEventType::owesTextBack()` sat in the enum with
 * a test and **no caller in `app/`**. It is called first now, and a non-missed
 * type throws rather than refusing, for the reason given at the call site.
 *
 * ⛔ **THE RECIPIENT MUST BE THE CALLER.** The contact is resolved from
 * `$call->customerId` and the message goes to `$permit->identifier` — two
 * lookups that can disagree, in which case the apology goes to somebody who did
 * not ring. `InboundCall`'s docblock asserted they could not disagree;
 * {@see self::callerIs()} is what makes that true.
 *
 * ## Quiet hours never hold this message — the T69 law
 *
 * `SL-2`'s rule and {@see MessageSender}'s guarantee 5: *"missed-call text-backs
 * and service replies are NEVER quiet-gated."* That is honoured by **passing
 * `OutreachPurpose::Transactional` and by nothing else** —
 * `ConsentService::stateRefusal()` returns before any window for a purpose
 * outside `isSubjectToDoNotCall()` (1618, 2207, 2552), and a second
 * implementation here would be two places that can disagree about when a person
 * may be texted. ⚠️ **The one line that keeps the law is therefore the
 * `Transactional` argument below**, which reads like a classification and is a
 * compliance control; a test drives it red by changing it to `Marketing`.
 *
 * ⛔ **AND THE PURPOSE IS AN HONEST CLASSIFICATION RATHER THAN A CONVENIENCE.**
 * `24` §3.3's standard is a condition the composer keeps, not a label the enum
 * grants: this message carries no offer, no incentive and no promotional
 * language, it answers a call the recipient placed seconds earlier, and
 * {@see self::compose()} is the boundary that keeps it so.
 *
 * ## Idempotency, and why the occasion comes from the event
 *
 * Voice webhooks redeliver. `App\Events\Voice\CallMissed::occasion()` owns the
 * string — *"two listeners deriving the occasion of this missed call
 * independently will eventually derive it differently, and the day they do, a
 * redelivered webhook sends the caller two apologies"* — and this class takes it
 * as a parameter rather than rebuilding it. The {@see SendKey} minted from it is
 * what the `outreach_messages` unique index arbitrates, so a second delivery
 * answers `SendOutcomeStatus::Duplicate` and debits nothing.
 *
 * ## What it does not do
 *
 * ⛔ **IT NEVER CREATES A CONTACT.** `InboundCall::$customerId` is null for a
 * first-time caller and that is *"the common case and not an error"* — the
 * conversation lane that handles the resulting thread creates the row, per that
 * class's own docblock. Creating one here would store a stranger's mobile number
 * on a basis nobody has established, in order to send a message that would then
 * be refused anyway for want of a consent record. It answers
 * {@see SendRefusalReason::NoIdentifier} instead, which is the true statement:
 * there is nobody here this platform can address.
 *
 * ⛔ **IT DOES NOT REPLY FROM THE NUMBER THE CALLER DIALLED, AND VOICE SPEC SAYS IT
 * SHOULD** (3183). `$call->numberId` and `$call->to` are carried on the event
 * and discarded here, because `NumberSelector::forSending()` takes no argument
 * and widening it changes which number `ReviewInviteSender` and `RunCampaignJob`
 * send from too. **Recorded as owed rather than bodged**: today the platform has
 * one number, so the observable behaviour is identical and the gap is a design
 * one — the day a tenant has their own number and a second pool number exists,
 * a caller can be answered from a number they have never seen.
 *
 * ⛔ **IT DOES NOT CHECK A SPAM-CALLER REGISTER, BECAUSE THERE IS NONE**
 * (3184). `29` §19.6 requires *"spam callers never transcribed or texted"* and
 * `16`'s automation 121 owns it; nothing in `app/`, `config/` or `database/`
 * implements a spam-call list or a caller-reputation score. This sender is the
 * first consumer that gate would have had.
 *
 * ⚠️ **IT DOES NOT CHECK THE KILL SWITCHES ITSELF.** {@see MessageSender}'s
 * guarantee 4 is kept inside `PlatformMessageSender`, which consults
 * `SendingGuard` before writing anything — the global halt, this tenant's pause,
 * and 2102's automatic complaint-rate trip. 2970–2979 records a halt that
 * stopped one sender out of two, because that sender had built a send path of
 * its own; **this one has no path of its own**, which is the cheapest way not to
 * become the third.
 */
final class MissedCallTextBack
{
    /**
     * The actor an audit row carries for a text nobody typed.
     *
     * `audit_log`'s vocabulary is `user:14` / `support:9`, and a system actor
     * has to be tellable from a person at a glance — `SendingGuard::SYSTEM_ACTOR`'s
     * reasoning, and its spelling.
     */
    public const string SYSTEM_ACTOR = 'system:missed-call-text-back';

    public function __construct(
        private readonly ConsentService $consent,
        private readonly MessageSender $sender,
        private readonly AuditService $audit,
    ) {}

    /**
     * Text the caller back, or say why not.
     *
     * @param  string  $occasion  `CallMissed::occasion()`'s string, passed in
     *                            rather than derived — see the class docblock.
     * @return SendOutcome|SendRefusalReason A bare reason means a gate refused
     *                                       **before** a send could even be
     *                                       keyed, so there is no outcome to
     *                                       return; a `SendOutcome` is the
     *                                       sender's own answer and may itself
     *                                       carry a refusal or a duplicate.
     *                                       ⚠️ **Refusal is an answer, not an
     *                                       error** — `ReviewInviteSender`'s
     *                                       fourth load-bearing property.
     */
    public function textBack(InboundCall $call, string $occasion): SendOutcome|SendRefusalReason
    {
        // ⛔ **`29` §19.6's BUILD-FAILING GATE: "answered call produces no
        // text-back."** `VoiceEventType::owesTextBack()` existed with a test and
        // **zero callers in `app/`** until 3179 — decision 3160 recorded
        // verifying the method and then not calling it, which is the same
        // writerless shape one layer in.
        //
        // ⛔ **LOUD RATHER THAN A REFUSAL, AND THE DISTINCTION IS
        // `PlatformMessageSender`'s STEP 1.** The event type is set by *our own*
        // webhook mapper, never by the recipient — so a non-missed type arriving
        // here is a wiring mistake, and answering "refused" would file it as a
        // fact about the person who called. The consequence of proceeding is
        // texting a member of the public who **just spoke to a human**
        // (`26` §3.3 names the hazard), so this must be the kind of failure
        // somebody investigates rather than one that accrues quietly in a run
        // row. It throws before the contact is even looked up: nothing written,
        // nothing debited, `AutopilotJob` records the run `failed` with this
        // message and releases the idempotency claim.
        //
        // ⛔ **AND THE FOURTH OUTCOME, WHICH THE FIRST VERSION OF THIS COMMENT
        // OMITTED — 314–316's SHAPE INSIDE THE FIX WAVE THAT QUOTES IT** (3192).
        // The three benign outcomes above are true and they are not the whole
        // list: `AutopilotJob` **rethrows**, so with `$tries = 3` the third
        // attempt puts the job in `failed_jobs` **carrying the whole
        // `InboundCall` and therefore the caller's mobile number in cleartext**
        // — database queue driver, no RLS on that table (3148), no crypto-shred
        // reaches it. **Reaching this throw through a queued job is a durable
        // privacy cost, not a loud log line.**
        //
        // ⚠️ **THIS SAID "NOTHING PRUNES IT" AND THAT STOPPED BEING TRUE ON
        // 2026-08-23** (8610-8639): thirty days, from `jobs:prune-failed`. ⛔
        // **THE THROW AND THE LISTENER'S GATE ARE BOTH UNCHANGED**, and the word
        // that survives is *durable*: a horizon is not an erasure path, so a
        // caller who asks us to erase them still has a payload from three weeks
        // ago outlive the request. The queued class is
        // `SendMissedCallTextBackJob`, which is what carries this object
        // (8725-8735).
        //
        // ✅ **WHICH IS WHY THE LISTENER NOW REFUSES TO DISPATCH FOR THESE
        // TYPES** — `TextBackMissedCaller::handle()` carries the same predicate
        // and logs instead, so the only reachable route to this throw is a
        // direct call from a caller that is not a queued job. This gate stays
        // because it is the chokepoint every future caller passes; the listener
        // is what keeps it from being reached with a payload.
        if (! $call->type->owesTextBack()) {
            throw new LogicException(
                "A {$call->type->value} call owes no text-back and this sender was asked to send one. "
                .'`29` §19.6 makes "answered call produces no text-back" a build-failing gate: the '
                .'caller has already spoken to somebody, and an apology for missing them is a message '
                .'to a member of the public about something that did not happen. Whatever mapped this '
                .'carrier event to CallMissed is what needs fixing.'
            );
        }

        $customer = $call->customerId === null
            ? null
            : Customer::query()->find($call->customerId);

        if (! $customer instanceof Customer) {
            // Nobody to address. See the class docblock: this is not a failure,
            // and it is not a contact waiting to be created here.
            return SendRefusalReason::NoIdentifier;
        }

        // ⛔ **THE GATE, AND THE ONLY BASIS THIS SENDER RECOGNISES.** A caller
        // with no consent record comes back `NoConsentRecord`, and nothing is
        // sent, written or debited.
        $decision = $this->consent->decide(
            $customer,
            OutreachChannel::Sms,
            // ⛔ **THE T69 LAW LIVES ON THIS ARGUMENT.** `Marketing` here would
            // hold a missed-call reply behind recipient-local quiet hours and,
            // in an install with no scrubbing register loaded, refuse it
            // outright. A test mutates this line.
            OutreachPurpose::Transactional,
        );

        $permit = $decision->permit;

        if (! $permit instanceof SendPermit) {
            // `decide()` always names a reason when it withholds a permit; the
            // fallback exists because the type allows null, and reporting a
            // silent `NoConsentRecord` would misname whichever gate refused.
            return $decision->reason ?? SendRefusalReason::NoConsentRecord;
        }

        // ⛔ **THE PERSON WE ARE ABOUT TO TEXT MUST BE THE PERSON WHO RANG**
        // (3180). `InboundCall`'s docblock already asserts this as a contract —
        // *"the number the text-back goes to is the number consent and
        // suppression are asked about"* — and until now nothing enforced it: the
        // contact came from `$call->customerId` and the message went to
        // `$permit->identifier`, two lookups that can disagree.
        //
        // ⚠️ **AFTER THE PERMIT AND NOT BEFORE, BECAUSE THE PERMIT'S IDENTIFIER
        // IS THE AUTHORITATIVE ONE.** `SendPermit`'s own docblock: it carries
        // *"the identifier the message goes to"*, resolved and trimmed by
        // `ConsentService::identifierFor()`, and *"a sender that reads the phone
        // number off the model can read a different phone number from the one
        // consent was checked against"*. Comparing `$call->from` against
        // `$customer->phone` would therefore compare the wrong pair. Nothing has
        // been written at this point, so the ordering costs one query.
        if (! $this->callerIs($call, $permit)) {
            // ⛔ **THE ONE REFUSAL ON THIS PATH THAT REACHES THE AUDIT LOG, AND
            // THE REASON IS THIS ENUM CASE'S OWN** (3193). Every other refusal
            // here is a fact about the recipient and belongs in
            // `automation_runs.output`, which is where they go;
            // `SendRefusalReason::CallerMismatch` is *"the only case here that
            // is about a third party rather than about the recipient"*, and what
            // it signals is a **data-integrity fault** — a webhook that resolved
            // the wrong contact, or a number edited between the call and the
            // job. `29` §2 rule 42 sends a sensitive action to the append-only
            // record, and a near-miss on texting an uninvolved member of the
            // public is one.
            //
            // ⚠️ **NO NUMBER, ON EITHER SIDE.** Naming the two numbers that
            // disagreed is the obvious thing to record and is exactly what must
            // not be: it would put a stranger's mobile *and* a contact's in a
            // table staff read, to document an event whose whole subject is that
            // one of them should not have been texted. The contact is named as
            // an entity, the call by its vendor handle, and an investigator
            // joins from there.
            $this->audit->record(
                action: 'voice.missed_call.caller_mismatch',
                actor: self::SYSTEM_ACTOR,
                entity: $customer,
                metadata: ['provider_call_id' => $call->providerCallId],
            );

            return SendRefusalReason::CallerMismatch;
        }

        $outcome = $this->sender->send(OutboundMessage::for(
            permit: $permit,
            body: $this->compose($customer),
            key: SendKey::for($permit, $occasion),
            purpose: OutreachPurpose::Transactional,
        ));

        if ($outcome->wasSent()) {
            // ⚠️ **THE AUDIT ROW CARRIES NO NUMBER AND NO WORDS.** `29` §2 sends
            // every sensitive action to the append-only log, and 627's warning
            // is that `metadata` is rendered to staff — so it names the call and
            // the send by their opaque handles only. The exact words a stranger
            // received live on the `outreach_messages` row the sender wrote,
            // which is the one record of them.
            $this->audit->record(
                action: 'voice.missed_call.texted_back',
                actor: self::SYSTEM_ACTOR,
                metadata: [
                    'provider_call_id' => $call->providerCallId,
                    'send_key' => $outcome->key->value,
                ],
            );
        }

        Log::info('TextBack outcome: '.json_encode($outcome));

        return $outcome;
    }

    /**
     * Is the person this permit addresses the person who just rang?
     *
     * ⚠️ **HASHES RATHER THAN STRINGS, AND {@see Identifier::hash()} RATHER THAN
     * A COMPARISON OF OUR OWN.** `+1 555 077 0001`, `5550770001` and
     * `+15550770001` are one number and three strings; that function is the one
     * normalisation consent, suppression and the registers already agree on, so
     * comparing anything else here would invent a fourth opinion about what two
     * numbers being equal means.
     *
     * ⛔ **A CALLER ID THAT WILL NOT NORMALISE IS A MISMATCH, NOT A PASS.** An
     * unparseable `from` cannot be shown to be the recipient, and the only
     * fail-open reading of that is *"text them anyway"*. The permit's own
     * identifier is guaranteed parseable — `ConsentService::decide()` refuses
     * `UnparseableIdentifier` before minting one — so a null on that side would
     * mean the gate above changed, and it is treated the same way.
     */
    private function callerIs(InboundCall $call, SendPermit $permit): bool
    {
        $caller = Identifier::hash($call->from, $permit->channel);

        if ($caller === null) {
            return false;
        }

        return $caller === Identifier::hash($permit->identifier, $permit->channel);
    }

    /**
     * The words a stranger receives, seconds after their call went unanswered.
     *
     * ⛔ **NO OFFER, NO INCENTIVE, NO PROMOTIONAL LANGUAGE.** `24` §3.3 permits a
     * transactional message *because* it stays one, and `OutreachPurpose`'s own
     * docblock warns that nothing in the enum can notice when a composer breaks
     * that. This method is the boundary, exactly as the invite composer is for
     * the review invite.
     *
     * ⚠️ **THE OPT-OUT SENTENCE IS NOT DECORATION.** Carrier rules require it,
     * and `Reply STOP` is honoured by the inbound webhook whether or not a
     * message advertises it — a recipient who does not know that has a stop path
     * in name only. HELP is deliberately not advertised, for the invite
     * composer's reason: it is answered inbound either way, and every character
     * spent here is a character of the message itself.
     *
     * ⛔ **THE "via GO AI EZ" DISCLOSURE IS UNCONDITIONAL HERE, AND THE FIRST
     * VERSION OF THIS METHOD MADE IT CONDITIONAL ON THE PERMIT'S LANE — WHICH
     * WAS WRONG** (3181). That version read `MessagingLane::Tenant => ''` on the
     * stated premise that *"Lane B rides the tenant's own TCR registration"*.
     * **Nothing enforces that premise and 2192 says it is false today**: *"a
     * tenant's number is lane `Platform`, not lane `Tenant`, even though it is
     * operationally theirs — the number belongs to the tenant while the brand
     * stays ours … a `Tenant` lane would assert the tenant holds their own TCR
     * brand, which is the one thing dedicated number allocation says they do not."*
     *
     * ⚠️ **THE TWO AXES WERE CONFLATED, AND THAT IS THE WHOLE DEFECT.**
     * `MessagingLane` on a permit is derived from `CapturedBy` — **who captured
     * the consent** — while the disclosure is a statement about **whose brand
     * the number carries**. They are independent, and reading one off the other
     * meant a tenant-captured contact received an unbranded, undisclosed SMS
     * from the shared GOAIEZ pool: a carrier-visible brand/content mismatch, on
     * the number every other tenant shares.
     *
     * ⛔ **SO THE DISCLOSURE FOLLOWS THE NUMBER, AND EVERY NUMBER THIS PLATFORM
     * CAN SEND FROM IS OURS.** `NumberSelector::forSending()` can only return a
     * `phone_numbers` row — shared pool or tenant-assigned, both provisioned
     * under our brand (2192) — or null, in which case the transport falls back
     * to the configured sender, which is also ours. **There is no reachable
     * state in which this sentence is false**, and the failure mode of stating
     * it anyway is one redundant true sentence rather than a missing required
     * one.
     *
     * ⚠️ **`ReviewInviteSender::compose()` STILL CARRIES THE CONDITIONAL VERSION
     * AND IS NOT CHANGED HERE** (3182). Two composers now disagree about one
     * rule, which is normally the thing to avoid — but the alternative is
     * editing a settled sender from a lane whose brief is the missed-call path,
     * and the disagreement is recorded rather than left to be found. The
     * systemic half of it is `29` §19.4, which nothing enforces at all.
     *
     * ⚠️ **NOT TRUNCATED, AND A LONG BUSINESS NAME MAY PUSH THIS INTO A SECOND
     * SEGMENT.** Decision 1570's rule, and the argument is the invite composer's:
     * the first thing a length limit cuts is the tail, and the tail is the
     * opt-out instruction and the disclosure. **A two-segment message costs a
     * fraction of a cent; a truncated compliance disclosure is a compliance
     * failure that reports as a successful send.**
     *
     * ⚠️ **IT IS NOT A REGISTRY KEY AND NOT A TENANT SETTING** — `CLAUDE.md`:
     * never add a tenant-facing toggle, opinionated defaults only. A wording an
     * owner can edit is a wording an owner can edit the disclosure out of.
     */
    private function compose(Customer $customer): string
    {
        $location = $customer->location;

        $business = $location instanceof Location ? trim($location->businessName()) : '';

        if ($business === '') {
            // A text that names nobody is both a carrier-filtering problem and a
            // compliance one — `24` §3.2 has the business as the sender of
            // record. The invite composer falls back the same way.
            $business = 'the business';
        }

        return "{$business}: sorry we missed your call. Reply to this text and we will help.\n"
            .'Sent via GO AI EZ. Reply STOP to opt out.';
    }
}
