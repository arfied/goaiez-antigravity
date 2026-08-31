<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Contracts\MessageSender;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\SendRefusalReason;
use App\Models\Customer;
use App\Models\OutreachMessage;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentService;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendKey;
use App\Services\Messaging\Outbound\SendOutcome;
use App\Services\Sms\ComplianceReplies;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;

/**
 * The welcome text a customer gets once, after they tick the box — the fourth
 * message the 10DLC campaign filing declares and the second one the product did
 * not send (3260).
 *
 * Until this existed, **the first message a consenting customer ever received
 * was the review invite**: an ask, with a link in it, arriving with no prior
 * text from us establishing who we are or how to stop. That is the sequence the
 * filing says does not happen, and it is also the sequence a carrier's spam
 * heuristics are built to notice.
 *
 * ## This is a real send and never an auto-reply, which decides everything else
 *
 * ⛔ **IT GOES THROUGH {@see MessageSender} AND NOT THROUGH THE PERMIT-FREE
 * REPLY PATH** (3268). That path exists for carrier-mandated answers to an
 * inbound message from the very number they answer, and {@see ComplianceReplies}'
 * whole design is that the caller can choose neither the recipient nor the
 * words. **Neither is true here**: this message is *originated* by us, to a
 * number a member of the public typed into a form, with no inbound message to
 * answer. Routing it down the permit-free path would be exactly the shortcut a
 * lint and three docblocks exist to prevent, and it would arrive at a person the
 * consent gate had never been asked about.
 *
 * ⛔ **AND NOT THE WAY THE REVIEW-INVITE PATH GOES EITHER** (2903, 2971). That
 * sender writes its own `outreach_messages` row and calls the texter directly,
 * so it has **no `SendKey` claim and no `MessageCostLedger` entry** — a known
 * gap, still open, and one this slice must not copy. Going through the one
 * sender gets all of `MessageSender`'s guarantees at once: the permit, the
 * idempotency claim, the transactional credit debit, the kill switches and
 * {@see SendingGuard}, and the row written before the wire call. ⚠️ **Both
 * classes are named in prose rather than with a `{@see}`**, for the reason
 * `ReviewInviteSender` itself records: Pint promotes one into a real `use`, and
 * an import here would read as an intent to call something this class
 * deliberately does not.
 *
 * ## Where it fires from, and what was rejected
 *
 * ⚠️ **`FeedbackSubmission::recordConsent()` DISPATCHES IT, PAST THE IDENTIFIER
 * CHECK, AND THAT IS THE WHOLE OF 3269.** Three other sites were considered:
 *
 *   - **`ConsentService::record()`**, the one place every consent capture goes
 *     through. **Refused.** `CustomerImports` writes consent records in bulk
 *     under an `ImportAttestation`, so a welcome text on every record written
 *     would text an entire uploaded list the moment a tenant imported it —
 *     turning 2098's attested import into a mass send nobody asked for. The
 *     confirmation belongs to the *surface a person used*, not to the table.
 *   - **`FeedbackSubmission::submit()`**, beside the two existing dispatches.
 *     **Refused, and this is the sharp one.** The identifier invariant that
 *     stops us writing SMS consent against a phone the submitter never saw the
 *     disclosure for lives *inside* `recordConsent()`, per channel: a resolved
 *     customer whose stored phone differs from the one typed this visit has that
 *     channel's record **skipped**. Dispatching from `submit()` sits above that
 *     check, so a confirmation would be dispatched for a consent event that
 *     never happened.
 *
 *     ⛔ **AND THE HARM IS NOT THE ONE THIS PARAGRAPH FIRST CLAIMED** (3275).
 *     The first draft said the hoist would *"text a number whose consent record
 *     was deliberately not written"*. That is false. `PlatformTexter` sends to
 *     `$permit->identifier`, which `ConsentService` reads off the **customer**
 *     and never off the submission, so a number a stranger typed cannot reach
 *     the wire. The claim was written before the mutation was run; the mutation
 *     then left the test green — 314–316's shape, caught inside the very slice
 *     that quotes it.
 *
 *     ⚠️ **THE REAL HARM, WHICH A TEST NOW DRIVES.** A stranger who knows only a
 *     customer's *email address* submits with the SMS box ticked and **no phone
 *     at all**. The invariant skips the record, correctly. A hoisted dispatch
 *     would still find a permit — minted from that customer's *prior* consent —
 *     and text their real phone, confirming an opt-in nobody performed. That is
 *     the case the placement actually buys, and it is the one to read before
 *     moving this dispatch.
 *   - **A verified point further along**, once the person has replied to
 *     something. **Refused as unbuildable**: nothing this platform sends before
 *     the confirmation could carry the verification, which is the circularity —
 *     the confirmation *is* the first message.
 *
 * ## A public unauthenticated endpoint can originate SMS to any typed number
 *
 * ⛔ **THIS IS OPEN, IT IS NOT CLOSED BY ANYTHING BELOW, AND THE FIRST WRITE-UP
 * OF IT WAS TOO SOFT** (3278). `/f/{slug}` needs no authentication.
 * `FeedbackSubmission::upsertCustomer()` resolves phone-first and, when **no
 * customer holds the typed number, creates one carrying it** — so
 * `recordConsent()`'s identifier invariant then compares that freshly written
 * value against the string it was written from, and **structurally cannot
 * fire.** A stranger POSTs any real mobile with the SMS box ticked; a customer
 * row appears; a `consent_records` row is written as `CapturedBy::Platform`
 * with a real IP hash and user agent, indistinguishable from a genuine opt-in;
 * and a text goes to somebody who has never heard of the tenant, over the GOAIEZ
 * 10DLC brand from our own pool number.
 *
 * ⚠️ **3275 CLOSED THE SIBLING CASE AND NOT THIS ONE.** That one needed an
 * *existing* customer and a *prior* consent record; this one needs neither, and
 * it is the larger of the two by far.
 *
 * ⛔ **AND THE FLAG IS A DEFERRAL RATHER THAN A MITIGATION** (3279). 3270 said
 * the seeded-off switch is "what holds the line", then instructed the operator
 * to arm it before `review_invite.sms_enabled` — so **the intended deployment
 * path is the exploit path**, and an off-by-default switch on a feature that
 * does nothing while off protects nobody the day it is turned on. That sentence
 * is corrected rather than deleted, because it was the reasoning actually used.
 *
 * ## Why the message is kept and the volume is bounded instead
 *
 * ⚠️ **SUPPRESSING THE CONFIRMATION WOULD MAKE THIS WORSE, WHICH IS THE
 * NON-OBVIOUS PART** (3280). A confirmation text is the *standard* mitigation
 * for a forged opt-in: it reaches the victim within seconds, names the business,
 * and hands them STOP — which, being platform-scoped (3261), ends every future
 * message from every tenant. Remove it and the forged `consent_records` row is
 * still written and still silent, and the victim's first contact becomes a
 * review invite **with a link in it**, weeks later, with nothing having told
 * them who we are. ⛔ **The root defect is that `/f/{slug}` accepts a phone the
 * submitter does not own — 334–337's territory, pre-existing, and NOT closed
 * here.** What this slice does is make that defect audible. So the containment
 * bounds the blast radius and deliberately does not silence the alarm.
 *
 * ⚠️ **TWO OTHER CONTAINMENTS WERE OFFERED AND BOTH ARE REFUSED, WITH REASONS
 * THAT ARE WORTH MORE THAN THE CHOICE** (3280):
 *
 *   - **Require a prior non-form interaction.** Refused: a first-time feedback
 *     submitter has none *by definition*, and that is the entire population this
 *     message exists for. It would leave a built feature that never fires —
 *     272's shape, arrived at deliberately.
 *   - **Verify the number first.** Refused, and the reason generalises:
 *     verifying a mobile means **sending a code to it**, which is the very send
 *     being abused. It relocates the abuse and doubles the traffic, and it puts
 *     an OTP flow on a public page. SMS verification cannot mitigate
 *     SMS-origination abuse.
 *
 * ✅ **WHAT SHIPS IS A HARD PER-TENANT DAILY CEILING**, server-side, degrading
 * gracefully — `29` §2 rule 43's own shape. See `dailyCeilingReached()` for why
 * it counts *sends per tenant* rather than requests per visitor: 336 leaves
 * every hashed-IP bucket in this codebase collapsed into one while
 * `TrustProxies` is unconfigured, so a limiter keyed on the requester is exactly
 * the control that is absent when it is needed, and a botnet defeats it anyway.
 *
 * ⚠️ **WHAT REMAINS AFTER THE CEILING, SAID PLAINLY.** One victim can still be
 * texted once per tenant per day. That is the residue of keeping the alarm
 * audible, and it is bounded by the ceiling, by one message per contact per
 * tenant for ever, by a credit per send, and — reactively — by 2102's automatic
 * complaint-rate trip, which reaches this path through `SendingGuard` inside the
 * one sender.
 *
 * ⚠️ **THE ESCALATION IS REAL EVEN THOUGH THE REVIEW INVITE IS REACHABLE THE
 * SAME WAY.** An invite needs a configured, confirmed destination, a rating at
 * or above the threshold and a globally-off switch; this needs a ticked box. It
 * is the first send in this product a public request could reach for **any**
 * tenant, and a self-registered no-card trial tenant carries 500 real SMS with
 * our brand on them — so the abuse controls 2066 already owes now have a second
 * caller, and this is the one that makes them urgent.
 */
final class OptInConfirmations
{
    /**
     * What the {@see SendKey} calls this send.
     *
     * ⚠️ **NOT A CONSTANT OCCASION — THE CUSTOMER ID IS APPENDED.** `SendKey`'s
     * docblock forbids a bare constant because it collapses every message a
     * caller ever sends a contact into one key. Here the collapse is wanted, but
     * only *within one contact*, so the id is what keeps two people apart even
     * if a number were ever to move between two customer rows.
     */
    private const string OCCASION = 'optin-confirmation';

    public function __construct(
        private readonly ConsentService $consent,
        private readonly MessageSender $sender,
        private readonly DefaultsRegistry $defaults,
    ) {}

    /**
     * Confirm this customer's opt-in, once, or say why not.
     *
     * ⚠️ **NULL IS STILL A REFUSAL AND NEVER AN ERROR** — `ReviewInviteSender`'s
     * fourth load-bearing property. The switch being off and the daily ceiling
     * are both ordinary states, and neither is surfaced to the person who just
     * submitted the form.
     *
     * ⛔ **BUT NULL IS NO LONGER THE ONLY REFUSAL SHAPE — 10240, PHASE 4.**
     * `ConsentService::permit()` was asked instead of `decide()`, so the one
     * gate on this path with a genuinely typed reason — the platform-wide
     * `opt_outs` register, the litigator list, the reassigned-numbers check,
     * an unloaded scrubbing register — threw it away one line later, the same
     * defect `ReviewInviteSender` and `MissedCallTextBack` both had before
     * they were fixed. **This method now asks `decide()` and returns the bare
     * reason it computed**, on `MissedCallTextBack::textBack()`'s own shape:
     * a `SendRefusalReason` means a gate refused before a send could even be
     * keyed, and a `SendOutcome` is the sender's own answer, which may itself
     * carry a refusal or a duplicate.
     *
     * ⚠️ **THE TWO GATES ABOVE THE PERMIT STAY BARE `null`, DELIBERATELY, NOT
     * AS AN OVERSIGHT THIS PHASE LEFT STANDING.** `SendOptInConfirmationJob::
     * confirm()`'s own docblock already named this precisely: closing it needs
     * a new {@see SendRefusalReason} case for a containment
     * ceiling, and that enum's own docblock makes a new case a conversation —
     * an `ownerSentence()`, an `isTemporary()` arm, a render site. That is a
     * conversation about `SendRefusalReason`, not a defect in this method: the
     * feature switch and the daily ceiling are facts about *us*, computed here
     * with no traversal to lose a reason from, unlike the permit.
     *
     * @param  string  $businessName  Resolved by the caller from the location, so
     *                                that the identity in this message is the
     *                                same one the review invite puts in its own.
     */
    public function send(Customer $customer, string $businessName): SendOutcome|SendRefusalReason|null
    {
        // ⚠️ **FIRST, BECAUSE IT IS THE ONLY GATE THAT COSTS NO QUERY AND CAN
        // REFUSE BEFORE ANYTHING ABOUT THIS PERSON IS LOOKED AT** — the review
        // invite's gate 1, for its reason. `!== true` so anything malformed
        // means do not send.
        if ($this->defaults->value('sms.optin_confirmation_enabled') !== true) {
            return null;
        }

        // ⛔ **THE CONTAINMENT, AND IT IS THE ONLY THING BOUNDING A FORGED
        // OPT-IN ONCE THE SWITCH IS ARMED** (3278–3281). Before the permit,
        // because minting one we are not going to act on writes a misleading
        // trail — `ReviewInviteSender`'s gate-2 ordering, for its reason.
        if ($this->dailyCeilingReached()) {
            return null;
        }

        // ⚠️ **THE PERMIT IS NOT A FORMALITY JUST BECAUSE CONSENT WAS CAPTURED
        // A MOMENT AGO.** Behind it sit the platform-wide `opt_outs` register,
        // the litigator list and the reassigned-numbers check — and the case
        // that matters is somebody who texted STOP last month and has now ticked
        // a box. `ConsentService::decide()` asks the withdrawal before the
        // consent record on purpose (its step 2 before its step 4), so that
        // person gets no confirmation until they text START.
        //
        // ⛔ **WHAT THE PERMIT DOES NOT CARRY ON THIS PURPOSE, STATED EXACTLY
        // RATHER THAN LEFT TO BE DISCOVERED** (3281). `Transactional` is right
        // for a genuine confirmation and it is what `24` §3.3's
        // existing-customer reading requires — but under a *forged* submission
        // there is no relationship, and nothing at send time can tell the two
        // apart. Precisely: the **litigator** register still applies
        // (`ComplianceList::appliesTo()` names both purposes), while **federal
        // and state DNC do not**, the `RegistryNotLoaded` fail-closed at
        // `ConsentService`'s step 3 does not, and 1618's ruling exempts it from
        // recipient-local quiet hours. **Flipping to `Marketing` was considered
        // and refused**: `RegistryNotLoaded` would refuse every confirmation
        // until three registers nobody has imported are loaded — the feature
        // would be inert, which is 272's shape — and quiet hours would *lose*
        // an evening consenter's confirmation rather than delay it, because
        // nothing here retries. The volume ceiling above is the answer instead.
        //
        // ⛔ **`decide()` RATHER THAN `permit()` — 10240, PHASE 4.** One
        // traversal computes the permit and the reason together; unwrapping to
        // `permit()` the way this line used to is the exact "caller chooses
        // the lossy view when it needs the reason" mistake `ReviewInviteSender`
        // documents having made on both its own channels.
        $decision = $this->consent->decide(
            $customer,
            OutreachChannel::Sms,
            // ⚠️ **`Transactional`, AND THE DEFAULT WOULD HAVE BEEN WRONG.**
            // `decide()` defaults to `Marketing`, which is quiet-hours gated —
            // and a confirmation held until 8am confirms something the person
            // did last night, arriving after they have forgotten doing it. This
            // follows an action they took seconds ago, carries no offer and
            // cannot be resent, which is what `24` §3.3 means by transactional.
            OutreachPurpose::Transactional,
        );

        if (! $decision->isGranted()) {
            return $decision->reason;
        }

        $permit = $decision->permit;

        $message = OutboundMessage::for(
            permit: $permit,
            body: $this->body($businessName),
            // ⚠️ **THE IDEMPOTENCY THAT MAKES A RE-CONSENT SAFE.** A customer who
            // resubmits the feedback form — the natural thing to do when a
            // submission appears to have gone nowhere — records consent again,
            // because consent records are append-only and a fresh tick is a
            // fresh grant. It must not be a second text. The key is derived from
            // the tenant, the channel, the hashed number and this occasion, so a
            // second send is refused by a unique index rather than by somebody
            // remembering to check.
            key: SendKey::for($permit, self::OCCASION.':'.$customer->getKey()),
            purpose: OutreachPurpose::Transactional,
        );

        return $this->sender->send($message);
    }

    /**
     * Whether this tenant has already sent as many confirmations today as it may.
     *
     * ⛔ **THIS IS THE ANSWER TO 3278, AND IT BOUNDS VOLUME RATHER THAN
     * SUPPRESSING THE MESSAGE** — see the class docblock for why suppressing was
     * refused. `29` §2 rule 43's shape exactly: a hard per-tenant cap, enforced
     * server-side, degrading gracefully. **Nothing throws, nothing is charged,
     * and nothing is surfaced to the person who submitted the form.**
     *
     * ⛔ **PER TENANT PER DAY, AND NOT PER VISITOR, BECAUSE THE PER-VISITOR
     * LIMITER CANNOT BE TRUSTED.** 336 records `TrustProxies` as unconfigured,
     * which collapses every hashed-IP bucket in this codebase into one global
     * bucket behind any reverse proxy — so a limiter keyed on the requester is
     * exactly the control that is not there when it is needed. **This counts
     * sends, not requests**, so no proxy configuration can weaken it and a
     * botnet buys nothing.
     *
     * ⛔ **THE COUNT IS NO LONGER EXACT, AND THE SENTENCE HERE CLAIMED IT WAS
     * UNTIL THE TWO BRANCHES MET** (3290). This paragraph read *"the
     * confirmation is the only `OutreachPurpose::Transactional` SMS this
     * application sends"* and named the day a second one appears as the day this
     * query needs a discriminator. **That day was the merge.** SM-001's
     * missed-call text-back writes `OutreachPurpose::Transactional` on the same
     * column, so its sends count against this ceiling — the two features were
     * built in sibling worktrees and neither suite could see the other.
     *
     * ⚠️ **WHAT IS STILL TRUE**: the review invite writes `review_request` and
     * the campaign runner writes `marketing`, both as free strings on the same
     * column, and tests pin that neither counts here.
     *
     * ⚠️ **THE OVER-COUNT IS LATENT RATHER THAN LIVE, WHICH IS WHY IT IS
     * RECORDED AND PINNED RATHER THAN FIXED IN A MERGE** (3291). Nothing in
     * `app/` dispatches `CallMissed` yet — `SendMissedCallTextBackJob`'s own
     * docblock says so — and this feature seeds off. **Both must be armed before
     * the defect can fire, and the discriminator is owed before either is.**
     * A test named for what is wrong pins the current behaviour, so it reddens
     * on the day the discriminator lands rather than passing silently.
     *
     * ⛔ **WHAT IT WOULD COST IF ARMED AS IT STANDS**: missed-call traffic is the
     * higher-volume feature by far, so it would consume a ceiling sized for
     * forged opt-ins, and a tenant's carrier-mandatory confirmations would stop
     * for the rest of the day for a reason no operator could see. Over-counting
     * is the safe direction for a safety cap and it is still the wrong number.
     *
     * ⚠️ **THE CEILING IS A CONSERVATIVE DEFAULT AND THE OWNER'S TO RAISE**
     * (3280). It is a safety limit rather than a policy figure, so a guess in
     * the strict direction is the right kind of guess — but it is still a guess,
     * and a tenant who legitimately hits it gets silence, which is why it is
     * logged.
     */
    private function dailyCeilingReached(): bool
    {
        $cap = $this->defaults->int('sms.optin_confirmation_daily_cap');

        if ($cap <= 0) {
            // ⚠️ **ZERO MEANS STOP, NEVER "UNLIMITED"** (1568's shape, on the one
            // control standing between a public form and a carrier).
            //
            // ⚠️ **THIS LINE IS A READABLE SHORT-CIRCUIT AND NOT THE SOLE CARRIER
            // OF THAT PROPERTY, WHICH THE MUTATION RUN IS WHAT ESTABLISHED**
            // (3282). Deleting this branch outright changes **nothing
            // observable**: `$sentToday < $cap` is already false for every
            // non-positive cap, so the refusal below answers zero and negatives
            // identically. Both were driven, and only breaking **both** turns
            // the zero case red. It is kept for two honest reasons — it says the
            // intent in one line, and it skips a `COUNT` that cannot change the
            // answer — but it must not be described as the guard, which the
            // first draft of this comment did.
            return true;
        }

        $sentToday = OutreachMessage::query()
            ->where('channel', OutreachChannel::Sms)
            ->where('purpose', OutreachPurpose::Transactional->value)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($sentToday < $cap) {
            return false;
        }

        // ⚠️ **LOGGED, BECAUSE A CEILING NOBODY SEES IS A FEATURE THAT STOPPED
        // WORKING SILENTLY** — 2095's concern about the 2,000/day mail ceiling,
        // one channel over. ⚠️ **The tenant and the figures, never the
        // recipient**: this path holds a member of the public's mobile number
        // and a log line is the one place it must not reach.
        Log::warning('A tenant reached its daily opt-in confirmation ceiling; further confirmations are held.', [
            'business_id' => Tenancy::id(),
            'cap' => $cap,
            'sent_today' => $sentToday,
        ]);

        return true;
    }

    /**
     * The exact words, and the only place they are written.
     *
     * ⚠️ **PUBLIC SO A TEST CAN READ IT BACK, AND SO THE FILING CAN BE CHECKED
     * AGAINST THE SHIPPED TEXT** rather than against a paragraph in a
     * spreadsheet. `ComplianceReplies::helpBody()` is public for the same reason
     * one class over.
     *
     * ⚠️ **EVERY CLAUSE IS A CARRIER REQUIREMENT AND NONE IS COPY.** Who is
     * texting and on whose behalf — standard on-behalf-of identity, the same
     * `"{Business} via GO AI EZ"` the invite carries. What the programme is.
     * **Message frequency** and **message and data rates**, the two disclosures
     * a reviewer looks for by name. `HELP` and `STOP`, which this application
     * really answers — `InboundKeyword::parse()` honours both and
     * {@see ComplianceReplies} replies to both.
     *
     * ⛔ **NO LINK, THE SAME AS THE AUTO-REPLIES** (2105). This one is arguably
     * worse for link filtering rather than better: it is the first message on a
     * fresh conversation, which is precisely the traffic carriers score hardest.
     *
     * ⚠️ **THE CONTACT CLAUSE IS CONDITIONAL, AND THE COLLISION BEHIND IT IS
     * RECORDED RATHER THAN RESOLVED IN CODE** (3271). Infobip lists a customer
     * care contact as **mandatory** and the filed text names
     * `support@goaiez.com`; decision 482 says an unmonitored address printed
     * where somebody is supposed to reach us is worse than none, and
     * `support.contact_email` therefore seeds empty. **This reads that registry
     * key, exactly as `ComplianceReplies::platformHelpBody()` does, so setting
     * one row satisfies both messages** — and until an operator sets it, both
     * ship without the clause the campaign declares. Hardcoding the address here
     * is the one thing that must not happen: it is 482's original defect,
     * rebuilt.
     */
    public function body(string $businessName): string
    {
        $contact = $this->defaults->value('support.contact_email');
        $contact = is_string($contact) ? trim($contact) : '';

        $sentences = implode(' ', array_filter([
            'you are signed up for texts about your visit.',
            'Msg frequency varies.',
            'Msg&data rates may apply.',
            $contact === '' ? null : "Contact: {$contact}.",
            'Reply HELP for help or STOP to opt out.',
        ]));

        $name = trim($businessName);
        $body = $name === '' ? 'GO AI EZ: '.$sentences : "{$name} via GO AI EZ: {$sentences}";

        // ⚠️ **THE ATTRIBUTION GOES, NEVER THE SENTENCE** — 1570's rule, and the
        // note on the constant itself. The clauses above are what the filing
        // declares and what a carrier checks for; the business name is the only
        // variable part, so it is the only part that may be dropped to fit.
        //
        // ⛔ **THIS BODY'S LENGTH IS NOW A PRICE, AND IT CROSSES THE BOUNDARY —
        // 2026-08-30 (12461, 12488).** A send costs `ceil(characters / 160)`,
        // and **measured**: without `support.contact_email` this body is **159**
        // characters and costs one credit; with that row set it is **189** and
        // costs two. ⚠️ **So an operator setting a support email doubles the
        // cost of every opt-in confirmation this platform sends**, from a
        // settings screen that says nothing about credits.
        // ⛔ **AND IT CANNOT BE COMPOSED AWAY.** This is the one message a
        // tenant has no choice about — it is what the 10DLC filing declares —
        // and the rule directly above forbids dropping a sentence to fit. **The
        // only lever is the contact row, and that one exists to fix 482.**
        // ⚠️ **Raised for the owner, not solved here**: whether a mandatory
        // compliance message should spend the tenant's grant at all is a pricing
        // question, and 12488 states it rather than answering it.
        return mb_strlen($body) > ComplianceReplies::CARRIER_FIELD_LIMIT
            ? 'GO AI EZ: '.$sentences
            : $body;
    }
}
