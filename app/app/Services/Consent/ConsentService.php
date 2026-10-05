<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\ComplianceList;
use App\Enums\ConsentEventType;
use App\Enums\ConsentType;
use App\Enums\ImpersonationCapability;
use App\Enums\LiftSource;
use App\Enums\MessagingLane;
use App\Enums\OptOutScope;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\SendRefusalReason;
use App\Enums\SuppressionReason;
use App\Enums\UsState;
use App\Models\ConsentRecord;
use App\Models\Customer;
use App\Models\OptOut;
use App\Models\SuppressionLift;
use App\Models\SuppressionListEntry;
use App\Services\AuditService;
use App\Services\Impersonation\Impersonation;
use App\Support\Identifier;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The one place this application decides whether a contact may be messaged.
 *
 * `29` §2: "The platform only sends where the platform owns the consent record",
 * and "a contact with no consent record can never be texted at all". COMP-01
 * requires that enforced at the service layer.
 *
 * WHAT MAKES THAT TRUE BEFORE ANY SENDER EXISTS. Row 4 owns messaging; nothing
 * here sends anything. So this class cannot rely on senders calling it — it has
 * to make the alternative impossible. permit() returns a SendPermit whose
 * constructor is private, and row 4's send signatures will require one. A send
 * path that skips consent is then a type error rather than a missing line.
 *
 * "GLOBALLY" IS TENANT-WIDE ON LANE B AND CANNOT BE ON LANE A — and the second
 * half of that sentence is the part this service does not yet implement.
 *
 * suppression_list is tenant-owned, so COMP-01's "honored instantly and
 * globally" covers every channel and campaign belonging to one business. **For
 * Lane B that is the right boundary.** `25` §1.3 gives each tenant its own
 * dedicated local number, so a person saying STOP to a dentist and a person
 * being messaged by their mechanic are two different sender relationships on two
 * different numbers, and one does not speak for the other.
 *
 * **For Lane A it is not, and the shipped behaviour is wrong.** Lane A is a
 * single verified toll-free number under the GO AI EZ brand (`25` §1.3 line 77),
 * and a carrier STOP is keyed on the sending number and the recipient — not on
 * whichever tenant happened to prompt the message. So when two Lane A tenants
 * message the same person, the first STOP ends that number's right to text them
 * at all, and a tenant-scoped lookup will happily authorise the second tenant's
 * next send from the number that was just opted out of. `25` §1.9 names
 * shared-fate deliverability as the main operational risk of platform sending;
 * this is that risk with a legal edge on it.
 *
 * ✅ THAT GAP IS CLOSED, AND THIS PARAGRAPH USED TO SAY IT WAS NOT. `opt_outs`
 * shipped platform-scoped (424–427) and `hasOptedOut()` is asked *before* the
 * tenant-owned list, so a STOP on Lane A's shared number now refuses the next
 * tenant's send. Decision 294 records the original error — an earlier version of
 * this docblock asserted the tenant-scoped reading was correct in general, when
 * it is correct for exactly one lane — and this correction is here rather than
 * only in DECISIONS.md because the sentence a reader acts on is the one in front
 * of them (384's lesson).
 *
 * WHAT THE SCRUBBING REGISTERS ADD ON TOP (`29` §2 rule 11). `permit()` now also
 * consults `SuppressionRegistry` — DNC, known litigators, reassigned numbers —
 * and `StateMessagingRules`. Both plug in behind an additive parameter, so no
 * caller changed. Two of those refusals are unconditional in production today
 * and are meant to be:
 *
 *   RegistryNotLoaded  the scrubbing registers a marketing send depends on have
 *                      not all been imported, and none is free to import. Every
 *                      *marketing* send is refused. ⚠️ It takes **all three** —
 *                      federal DNC, litigator, reassigned numbers — since 1600,
 *                      and **on the channel being sent** since 1611; any one of
 *                      them, on any channel, used to satisfy it, which let the
 *                      cheapest list stand in for the registry that matters and
 *                      let a register loaded for email open the SMS gate.
 *   StateUnknown       this customer has no recorded state, and several states
 *                      are stricter than federal. Every *marketing* send is
 *                      refused. ⚠️ Since 1594 that means *nobody answered*
 *                      rather than *nobody could* — the column has a writer.
 *
 * Neither touches transactional messages, which is every message this product
 * sends (`24` §3.3) — so the cost today is nil and the constraint is met by
 * row 4's first marketing campaign at build time rather than by a plaintiff.
 */
final class ConsentService
{
    /**
     * Channels that ride the phone number, and therefore the lane.
     *
     * `25` §1.3's lanes are literally phone numbers — a verified toll-free one
     * for Lane A, one dedicated local number per tenant for Lane B. Email has no
     * lane because email has no number, which is why the lane can stay a single
     * column without becoming the lossy thing is_suppressed was.
     */
    private const array SMS_FAMILY = [OutreachChannel::Sms, OutreachChannel::Whatsapp];

    public function __construct(
        private readonly AuditService $audit,
        private readonly SuppressionRegistry $registry,
        private readonly StateMessagingRules $stateRules,
        private readonly Impersonation $impersonation,
        private readonly IdentifierHashEpochs $epochs,
    ) {}

    /**
     * The gate. Null is the safe default and the common case.
     *
     * ⚠️ THE PURPOSE PARAMETER DEFAULTS TO MARKETING, WHICH IS THE RESTRICTED
     * ONE. `24` §3.4 makes every scrubbing rule turn on this axis — DNC applies
     * to marketing, litigator suppression applies to everything — so a caller
     * who does not think about it gets the safe answer and a caller who wants
     * the permissive one has to say so. Row 4's review invites pass
     * `Transactional` deliberately, and `24` §3.3 is the authority for their
     * doing so: "review requests stay strictly transactional". That sentence is
     * a *condition* — an invite carrying a discount code has made itself
     * marketing, and nothing here can notice.
     *
     * The signature is additive, so no existing caller changes. That was the
     * design goal recorded when `opt_outs` shipped: every remaining scrubbing
     * rule plugs in here without touching a caller.
     *
     * @see decide() for the same answer with its reason attached.
     */
    public function permit(
        Customer $customer,
        OutreachChannel $channel,
        OutreachPurpose $purpose = OutreachPurpose::Marketing,
    ): ?SendPermit {
        return $this->decide($customer, $channel, $purpose)->permit;
    }

    /**
     * The gate, with the reason it answered as it did.
     *
     * ⚠️ ONE TRAVERSAL, TWO VIEWS. `permit()` is this method with the reason
     * discarded, so the two cannot disagree. Two implementations of one rule is
     * how the second one quietly stops matching the first — decision 437's
     * lesson about a list written in two places, applied to a decision.
     *
     * THE ORDER OF THESE CHECKS IS THE DESIGN, NOT AN IMPLEMENTATION DETAIL.
     *
     *   0. the two states in which this row is not a person to message at all,
     *      before everything, because neither needs an identifier:
     *
     *        merged away  this row was folded into another contact, so the
     *                     message belongs to that one. Asked FIRST of the two,
     *                     because a merged contact that is also archived should
     *                     tell the operator where the person went rather than
     *                     that somebody hid an empty row
     *        deleted      the owner deleted the contact (1540). Asked before
     *                     archive because it is the stronger statement and the
     *                     only one of the three whose undo expires, so an
     *                     operator told "archived" about a contact whose
     *                     seven days are running would look for a restore
     *                     button on the wrong screen
     *        archived     the owner hid the contact, so no channel and no
     *                     purpose survives it
     *
     *      ⚠️ All three are asked HERE and never in a list query, on decision
     *      1327's ruling: a send path hydrating a customer id from a queue
     *      payload never runs the list query, which is 398's caller exactly.
     *      And none of them is a suppression — see SendRefusalReason::Archived,
     *      ::Deleted and ::MergedAway.
     *   1. the identifier, because every later check keys on it
     *   2. STOP, before consent — a withdrawal must beat a later-timestamped
     *      consent record. The failure worth engineering against is texting
     *      somebody who said STOP, not failing to text somebody who re-consented.
     *      There is no "revoked" consent record to look for: consent_records is
     *      append-only and a withdrawal is a suppression entry, which is exactly
     *      why this step exists and why it comes early
     *   3. the registers that need no consent date — litigator and DNC
     *   4. the consent record itself. `29` §2 rule 6: a contact with no consent
     *      record can never be texted at all
     *   5. reassignment, which is *after* the record because it is the only
     *      check that needs the record's date. A number reassigned after consent
     *      was captured means the consent belongs to somebody else
     *   6. the state rules, last, because they are the only checks that can turn
     *      on the hour and the strength of the record found in step 4
     */
    public function decide(
        Customer $customer,
        OutreachChannel $channel,
        OutreachPurpose $purpose = OutreachPurpose::Marketing,
    ): SendDecision {
        if ($customer->merged_into_id !== null) {
            return SendDecision::refused(SendRefusalReason::MergedAway);
        }

        if ($customer->deleted_at !== null) {
            return SendDecision::refused(SendRefusalReason::Deleted);
        }

        if ($customer->archived_at !== null) {
            return SendDecision::refused(SendRefusalReason::Archived);
        }

        $identifier = $this->identifierFor($customer, $channel);

        if ($identifier === null) {
            return SendDecision::refused(SendRefusalReason::NoIdentifier);
        }

        if (Identifier::hash($identifier, $channel) === null) {
            // Named separately from OptedOut because it is a different fact with
            // a different fix. `hasOptedOut()` also fails closed on this, and
            // reporting that as "this person opted out" would send an operator
            // looking for a STOP that never happened.
            return SendDecision::refused(SendRefusalReason::UnparseableIdentifier);
        }

        // ⛔ **BEFORE THE SUPPRESSION LOOKUP, BECAUSE IT IS THE LOOKUP'S OWN
        // PRECONDITION** (8080). Every suppression this platform stores is a
        // keyed hash and the key is `APP_KEY`; after a rotation a freshly
        // computed hash matches none of them, so `isSuppressed()` answers false
        // for everybody and this method falls straight through to the consent
        // record — which exists, because that is why the person was texted in
        // the first place. Measured, not reasoned about: `refused opted_out`
        // before the rotation and `GRANTED` after it.
        //
        // ⚠️ NAMED APART FROM `OptedOut`, the way `UnparseableIdentifier`
        // already is: *the register cannot answer* and *this person never opted
        // out* must not be the same answer, and only one of them has a fix an
        // operator can carry out.
        if (! $this->epochs->isReadable()) {
            return SendDecision::refused(SendRefusalReason::SuppressionUnreadable);
        }

        if ($this->isSuppressed($identifier, $channel)) {
            return SendDecision::refused(SendRefusalReason::OptedOut);
        }

        // ⚠️ BEFORE THE CONSENT LOOKUP, because these do not depend on it, and
        // a litigator must be refused whether or not a record exists. Passing
        // no consent date makes every reassignment apply, which is the safe
        // reading — but reassignment is re-checked below against the real date
        // once there is one, so this call deliberately asks only about the
        // registers that are dateless.
        $undated = $this->registry->refusalsFor($identifier, $channel, $purpose);

        if ($refusal = $this->firstDatelessRefusal($undated)) {
            return SendDecision::refused($refusal);
        }

        $record = ConsentRecord::query()
            ->where('customer_id', $customer->id)
            ->where('channel', $channel)
            // Postgres sorts NULL first on a DESC ORDER BY, so a plain
            // ->latest('created_at') would put a null-dated row ahead of every
            // dated one and the ->latest('id') tiebreak would never run. Nothing
            // writes a null created_at today, but capturedBy/lane are read off
            // whichever record this picks, so the wrong row silently changes
            // which lane and number a message goes out on. Do not simplify this
            // back to ->latest('created_at').
            ->orderByRaw('created_at DESC NULLS LAST')
            ->latest('id')
            ->first();

        // ⚠️ A CALLER WHO ONLY RANG LET US REPLY ABOUT THAT CALL, NOTHING MORE (owner ruling D-1, 2026-10-05). Read here,
        // ahead of the register check below, so a marketing send to such a contact is refused for the reason that stays true
        // rather than for one an import would clear. Every earlier refusal (opt-out, do-not-call, litigator) keeps its place.
        if ($record?->consent_type === ConsentType::ImpliedByCall && $purpose !== OutreachPurpose::Transactional) {
            return SendDecision::refused(SendRefusalReason::RepliesOnly);
        }

        // ⚠️ THE MARKETING FAIL-CLOSED. No scrubbing register has ever been
        // loaded, so a marketing send cannot be proved clean against a list we
        // are required to consult. Transactional is untouched, which is every
        // message this product sends today (`24` §3.3), so this costs nothing
        // until somebody builds a campaign — and then it costs them a
        // conversation rather than a violation.
        // ⚠️ THE SEND'S OWN CHANNEL, NOT "ANY CHANNEL" (1611). A federal DNC
        // extract imported against the wrong `--channel` flag used to satisfy
        // this gate for a channel it could never refuse anybody on.
        if ($purpose->isSubjectToDoNotCall() && ! $this->registry->isLoaded($channel)) {
            return SendDecision::refused(SendRefusalReason::RegistryNotLoaded);
        }

        if ($record === null) {
            return SendDecision::refused(SendRefusalReason::NoConsentRecord);
        }

        // Re-asked with the consent date in hand. A record with no created_at
        // gives null, and null means every reassignment applies — an undated
        // record cannot be shown to predate anything.
        $dated = $this->registry->refusalsFor(
            $identifier,
            $channel,
            $purpose,
            $record->created_at === null ? null : CarbonImmutable::instance($record->created_at),
        );

        if (in_array(ComplianceList::ReassignedNumber, $dated, true)) {
            return SendDecision::refused(SendRefusalReason::NumberReassigned);
        }

        if ($refusal = $this->stateRefusal($customer, $record, $purpose, $channel)) {
            return SendDecision::refused($refusal);
        }

        // The identifier resolved above is the one suppression was checked
        // against, so it is the only one the permit may name. A sender that
        // re-reads the phone number off the customer could read a different one.
        return SendDecision::granted(SendPermit::grant($record, $identifier));
    }

    /**
     * The first refusal among the registers that do not depend on a date.
     *
     * `ReassignedNumber` is deliberately excluded: it is asked again below with
     * the consent record's date, and answering it here — where the date is
     * unknown and every reassignment therefore applies — would refuse every
     * customer whose number has ever changed hands, including the ones who
     * consented afterwards.
     *
     * @param  list<ComplianceList>  $refusals
     */
    private function firstDatelessRefusal(array $refusals): ?SendRefusalReason
    {
        foreach ($refusals as $list) {
            $reason = match ($list) {
                ComplianceList::Litigator => SendRefusalReason::Litigator,
                ComplianceList::FederalDnc, ComplianceList::StateDnc => SendRefusalReason::DoNotCall,
                ComplianceList::ReassignedNumber => null,
            };

            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * The state mini-TCPA half of `29` §2 rule 11.
     *
     * ⚠️ MARKETING ONLY, THE SAME ASYMMETRY AS THE GATING ACK AND
     * `send_review_requests` (381). These statutes govern telephone
     * solicitation; a transactional message arising from a relationship the
     * person already has is not one, and blocking those would stop the entire
     * review engine to enforce a rule that does not reach it.
     *
     * ⚠️ AN UNKNOWN STATE IS A REFUSAL, NOT A FALLBACK TO FEDERAL. Rule 11's own
     * justification is that several states are stricter than federal, so
     * federal-only is the permissive branch applied exactly where the stricter
     * rule was meant to bind.
     *
     * ✅ **THE STATE IS NOW ANSWERABLE, AND THIS PARAGRAPH USED TO SAY IT WAS
     * NOT** (1594). `customers.region_code` had no writer at all, so this was
     * the branch every production send took; row 4 slice 5 gave it two —
     * `CustomerEditor::setRegion()` behind the contact profile, and a `state`
     * column on an imported list. What did **not** change is the answer for a
     * contact nobody has answered for: still `StateUnknown`, still a refusal,
     * because a blank field and a state with no statute are different facts and
     * only one of them is safe to send under.
     *
     * ⚠️ **AND NOTHING DERIVES IT** (1595). Not from the phone number's area
     * code, not from the location's or the business's address, not from a
     * LERG-derived range table. Each is a guess that becomes policy silently;
     * see `CustomerEditor::setRegion()`, which argues all three.
     *
     * ⛔ **A STATE WITH NO ROW NO LONGER PERMITS** (1609). This method was the
     * only quiet-hours enforcement in `app/`, and when `for($state)` returned
     * null it returned null too — correct as "no state rule *stricter than
     * federal*", and catastrophic as an answer, because nothing expressed what
     * federal says. It cost nothing while `StateUnknown` refused every marketing
     * send; slice 5 removed that, and `state_messaging_rules` ships empty, so
     * every state was about to become a no-row state at once.
     * `StateMessagingRules::platformFloorRefusal()` is the floor.
     *
     * ⚠️ **AND IT IS A FLOOR RATHER THAN A DEFAULT — THIS SENTENCE SAID THE
     * OPPOSITE FOR ONE COMMIT** (1617). It read *"a state row overrides it
     * rather than stacking with it"*, which is what 1609 built and what the
     * brief asked for, and it is wrong: 47 CFR 64.1200(c)(1) binds nationwide,
     * so a state may be stricter and cannot authorise what federal forbids. The
     * prohibited bands **union**. A counsel row narrower than federal would
     * otherwise have permitted an hour federal refuses, on the strength of a row
     * somebody entered to be more careful.
     *
     * ⚠️ **`$channel` ADDED, WAVE 39 LANE B, FOR ONE CARVE-OUT ONLY.** 1618 ruled
     * the marketing-only short-circuit below about *messages* — the review
     * invite fires seconds after a submission and a window there would lose it
     * rather than delay it. **A day-14 invoice-chasing call (`§46.5` of the
     * governing plan, `CLAUDE.md` §Deliberate spec overrides) is `Transactional`
     * by the same classification and has never been put to the owner as a
     * *call*.** A phone ringing outside a legal daytime window is a materially
     * different event from a text arriving then — it cannot be read later, it
     * interrupts whoever answers, and 47 CFR 64.1200(c)(1) is written against
     * telephone *solicitation calls* first and texts by extension. Until the
     * owner is asked that question directly (recorded open in `DECISIONS.md`,
     * wave 39 lane B), this method takes the conservative reading for `Voice`
     * only: the calling window applies regardless of purpose. **Every other
     * channel is byte-for-byte unchanged** — the added parameter only ever
     * changes the answer when `$channel === OutreachChannel::Voice`.
     *
     * ⚠️ **AND THIS IS NOT AN UNPRECEDENTED SHAPE — THE WAVE-39 LEAD CAUGHT
     * THIS BEFORE IT WAS WRITTEN AS THOUGH IT WERE.** `RecoveryCheckInSender::
     * attemptChannel()` already calls `decide()` for a `Transactional` send and
     * then, separately, calls `daytimeWindowRefusal()` a second time at the
     * call site — layering the window back on top of exactly the purpose 1618
     * excuses from it, for its own argued reason (10601: a sweep-chosen message
     * rather than an immediate reply). That is a *caller* choosing to apply the
     * window on top of the gate; this carve-out is the *gate itself* answering
     * differently for one channel. Different mechanism, same policy point: a
     * `Transactional` purpose has never meant "never held to quiet hours" in
     * this codebase, only "not held to it *by the gate, by default*."
     */
    private function stateRefusal(
        Customer $customer,
        ConsentRecord $record,
        OutreachPurpose $purpose,
        OutreachChannel $channel,
    ): ?SendRefusalReason {
        // ⚠️ **THE BODY BELOW MOVED INTO `daytimeWindowRefusal()` AT T176 P14,
        // AND THIS METHOD IS NOW THE PURPOSE GATE IN FRONT OF IT.** Nothing
        // about what the window says changed; what changed is that a second
        // caller needs the same answer for a message 1618 does not cover. See
        // that method for who and why. The split is deliberate rather than a
        // convenience: two spellings of a quiet-hours comparison is the failure
        // `StateMessagingRules::windowRefusal()` names in its own docblock, and
        // the arithmetic is already only in `StateMessagingRule::prohibits()`
        // for exactly that reason.
        //
        // ⛔ **THIS LINE IS THE MARKETING-ONLY RULING, AND IT OVERRIDES `24`
        // §3.4 AND `29` §754 RATHER THAN IMPLEMENTING THEM** (1618). Both word
        // the quiet-hours row as applying to **All** message types, three places
        // in this codebase quoted that wording, and none of them described this
        // code: a `Transactional` send returns here and is never held to a
        // window. **The owner ruled that it should not be** — the review invite
        // fires the moment somebody submits our own feedback form, so it answers
        // a just-completed interaction rather than soliciting one, and federal
        // quiet hours target solicitations. ⚠️ **The alternative loses messages
        // rather than delaying them**: `ReviewInviteSender` returns null on a
        // refusal and never retries, and the deferral scheduler is doc `43`,
        // blocked on the undelivered `42`. So an evening feedback submission
        // would produce no invite at all, ever. Do not "correct" this to match
        // the spec wording; a test in `CustomerRegionTest` pins it.
        //
        // ⛔ **`Voice` NEVER TAKES THIS SHORT-CIRCUIT, REGARDLESS OF PURPOSE**
        // (wave 39 lane B). See this method's own docblock for the argument and
        // the open question left for the owner. Every other channel's behaviour
        // is exactly what it was: this line only starts refusing on the arm it
        // used to grant, and only for the one channel nothing could reach before
        // today.
        if ($channel !== OutreachChannel::Voice && ! $purpose->isSubjectToDoNotCall()) {
            return null;
        }

        return $this->daytimeWindowRefusal($customer, $record->consent_type);
    }

    /**
     * Why the recipient-local legal daytime window is closed for this contact
     * right now, or null when it is open.
     *
     * ⛔ **THIS IS `stateRefusal()`'s BODY WITH THE PURPOSE GATE LIFTED OFF, AND
     * THAT IS THE WHOLE OF WHAT MAKES IT PUBLIC** (T176 P14). It is not a second
     * quiet-hours implementation and must never become one: the state row, the
     * federal floor, the union of the two, the zone list and the arithmetic are
     * all exactly where 1609, 1617 and 1620 put them, and the private caller one
     * method up now goes through here too, so the two cannot disagree.
     *
     * ⛔ **IT EXISTS FOR THE INVITE FOLLOW-UP REMINDER AND FOR NOTHING ELSE
     * TODAY.** T176 §3's R23 draws the line this method sits on: a conversation
     * *response* is never hour-gated, but *"the first outbound of a review
     * request or reactivation to a contact rides the legal daytime window"*.
     * 1618 ruled the **immediate** invite out of that for a concrete reason —
     * `ReviewInviteSender` returns null on a refusal and never retries, so a
     * window applied there would silently *lose* every evening submission's
     * invite rather than delay it. **That reason does not transfer to a
     * reminder.** A reminder is chosen by a sweep that runs every fifteen
     * minutes and asks again tomorrow, so a closed window holds it rather than
     * destroying it — which is the exact condition 1618's argument turned on.
     *
     * ⚠️ **THE PURPOSE STAYS `Transactional` AND THIS IS NOT A CONTRADICTION.**
     * `24` §3.3 is what permits a review request at all, and a nudge to post the
     * feedback somebody already wrote is the same message with the same
     * condition on it. What the caller asks for here is the *hour*, not a
     * reclassification: passing `Marketing` to `permit()` would additionally
     * engage the Do Not Call registers and rule 7's prior-express-written-
     * consent requirement, which `24` §3.3 does not ask of a review request and
     * which would be a change to what the invite path means rather than an
     * addition to it.
     *
     * ⚠️ **`StateUnknown` IS AMONG THE ANSWERS AND THE CALLER MUST TREAT IT AS A
     * HOLD.** A recipient-local window cannot be evaluated without a recipient
     * locale, and 1568's ruling stands: falling back to federal is the
     * *permissive* branch applied exactly where the stricter rule was meant to
     * bind. So a contact nobody has recorded a state for gets no reminder — and
     * unlike every other caller of this rule, that refusal is recoverable,
     * because the sweep asks again once somebody fills the field in.
     */
    public function daytimeWindowRefusal(Customer $customer, ?ConsentType $consentType): ?SendRefusalReason
    {
        // ⚠️ STILL A REFUSAL WHEN IT IS NULL, AND NOW IT IS NULL BECAUSE NOBODY
        // ANSWERED RATHER THAN BECAUSE NOBODY COULD (1594). `customers.
        // region_code` is the recipient's jurisdiction and it has a writer as of
        // row 4 slice 5 — `CustomerEditor::setRegion()` from the contact's
        // profile, and a `state` column on an imported list. A contact nobody
        // has answered for is still refused here, and falling back to federal
        // would still be the *permissive* branch applied exactly where rule 11's
        // stricter rule was meant to bind, reporting as compliant (1568).
        $state = UsState::normalise($customer->region_code);

        if ($state === null) {
            // ⚠️ COVERS BOTH "NOBODY ANSWERED" AND "SOMETHING UNRECOGNISABLE IS
            // STORED". The CHECK rebuilt at 1596 forbids the second, so reaching
            // it means a row arrived by a path that bypassed the schema — and a
            // code no legislature uses matches no rule, which is the branch that
            // used to *permit*. Refusing is the same answer for both, and it is
            // the only one that is true of both.
            return SendRefusalReason::StateUnknown;
        }

        $timezone = $customer->location?->timezone;

        if ($timezone === null) {
            // ⚠️ `24` §3.4 SAYS "IN THE LOCATION'S TIMEZONE" AND THE COLUMN IS
            // NULLABLE. A quiet-hours window evaluated in the server's timezone
            // passes at the wrong hours and reports as working, so no timezone
            // means no hour to check against — and that is a refusal, not a
            // pass.
            return SendRefusalReason::QuietHours;
        }

        $zones = $this->quietHoursZones($timezone, $state);
        $rule = $this->stateRules->for($state->value);

        // ⚠️ NO ROW MEANS NO STATE RULE **STRICTER THAN FEDERAL**, WHICH IS NOT
        // THE SAME AS NO RULE (1609). Most states have no mini-TCPA and that is
        // the common branch; it used to return null and permit at any hour.
        //
        // ⚠️ **THE STATE ROW IS ASKED FIRST SO THE DURABLE REFUSAL WINS THE
        // REPORT** (1620). Both gates refuse, so no send escapes either way and
        // the ordering decides only which `SendRefusalReason` an operator
        // debugs from. Asked floor-first, a state that requires prior express
        // *written* consent reported `QuietHours` for eleven hours a day — a
        // reason that reads as "try again later" standing in front of one that
        // will still refuse at noon and needs a different consent record to
        // clear. The clock-dependent answer must not hide the permanent one.
        //
        // ⚠️ **AND THIS IS WHY `for()` IS FETCHED ABOVE RATHER THAN BELOW.** A
        // review asked for the lookup to move under the floor's short-circuit;
        // it cannot, because the row is what carries the durable refusal. The
        // cost is one indexed lookup on a platform table counsel will fill with
        // at most fifty rows, and nothing is fetched that is not used.
        if ($rule !== null) {
            if ($refusal = $this->stateRules->refusalFor($rule, $zones, $consentType)) {
                return $refusal;
            }
        }

        // ⛔ **THE FLOOR IS APPLIED WHETHER OR NOT A STATE ROW EXISTS, AND THE
        // FIRST VERSION OF THIS FIX GOT IT WRONG** (1617). 1609 shipped the
        // floor as a *fallback* — a state row replaced it — which is what a
        // "default" means and is not what a floor means. **47 CFR
        // 64.1200(c)(1) binds nationwide; a state may be stricter and cannot
        // authorise what federal forbids.** A counsel row narrower than federal
        // (say 22:00–07:00) would have permitted 21:30 in that state, silently,
        // on the strength of a row somebody entered to be *more* careful.
        //
        // This is `CLAUDE.md`'s recorded shape — "a fix wave is a change like
        // any other, and two of the three defects introduced by fixes were the
        // same shape as the thing being fixed". The thing being fixed was a
        // missing floor; the defect was a floor that could be overridden away.
        //
        // It is also the owner's own ruling on the timezone question one line
        // up, applied to the other axis: **strictest of both, refuse if either
        // says closed.** The prohibited bands union rather than replace, which
        // is what obeying two statutes at once actually means — not, as the
        // objection to it went, "a window no legislature wrote". Order is
        // irrelevant to *that*: a band closed in either refuses whichever is
        // asked first.
        return $this->stateRules->platformFloorRefusal($zones);
    }

    /**
     * Every clock this send has to be inside the permitted window in.
     *
     * ⚠️ **THE OWNER'S RULING IS THE STRICTEST OF BOTH, BECAUSE THE SPECS
     * DISAGREE** (1609). `29` line 294 says quiet hours are in the *recipient's*
     * timezone; `29` line 754 and `24` §3.4 say the *location's*. Those name the
     * same clock only when the customer lives where the business does, and the
     * gap between them is a real hour: a Pensacola business on `America/Chicago`
     * texting a Miami customer at 19:30 Central is texting them at 20:30
     * Eastern, past a Florida window that closes at 20:00. So both are honoured
     * and a window closed in either refuses.
     *
     * ⛔ **THIS IS NOT THE DERIVATION 1598 FORBIDS, AND THE NEXT READER WILL
     * THINK IT IS.** 1598 refuses deriving a **stored fact** — `locations.
     * timezone`, the zone one named business keeps its hours in — from a state,
     * because a state-to-zone table is wrong for millions of people and wrong in
     * the direction that texts them earlier. Nothing here writes anything and
     * nothing here asserts which zone the customer is in. `UsState::timezones()`
     * returns the **set** of clocks the state contains, and a straddling state
     * contributes all of them — so Florida widens the refusal rather than
     * picking a side. The failure 1598 names (a Panhandle business filed under
     * `America/New_York` losing an hour of its window) cannot be produced by a
     * rule that only ever adds vetoes.
     *
     * @return list<string>
     */
    private function quietHoursZones(string $businessTimezone, UsState $state): array
    {
        return array_values(array_unique([$businessTimezone, ...$state->timezones()]));
    }

    /**
     * Where a message on this channel would actually go.
     *
     * WhatsApp rides the phone number, which is also why it shares the lane:
     * `25` §1.3's lanes are phone numbers, not abstractions.
     */
    private function identifierFor(Customer $customer, OutreachChannel $channel): ?string
    {
        $identifier = match ($channel) {
            OutreachChannel::Sms, OutreachChannel::Whatsapp, OutreachChannel::Voice => $customer->phone,
            OutreachChannel::Email => $customer->email,
        };

        if ($identifier === null) {
            return null;
        }

        $identifier = trim($identifier);

        return $identifier === '' ? null : $identifier;
    }

    /**
     * ⚠️ **LOOPS `$channel->suppressionSharedWith()` RATHER THAN WIDENING THE
     * TWO PREDICATES BELOW DIRECTLY.** For every channel but `Voice` that list
     * is `[$channel]`, so this is one iteration and behaves exactly as before —
     * `hasOptedOut()` and `standingEntryQuery()` are unchanged, and so is every
     * lift each of them compares against, because each iteration still asks a
     * single channel's own rows about a single channel's own lifts. The
     * alternative — a `whereIn` across the family inside those two methods —
     * would also have to widen `liftsClearing()`'s generation matching to stay
     * correct, since a lift is numbered per (identifier, channel); looping the
     * already-correct per-channel query is the smaller, more auditable change
     * (`suppressionSharedWith()`'s own docblock has the compliance argument).
     */
    private function isSuppressed(string $identifier, OutreachChannel $channel): bool
    {
        foreach ($channel->suppressionSharedWith() as $sharedChannel) {
            if ($this->hasOptedOut($identifier, $sharedChannel)) {
                return true;
            }

            if ($this->standingEntryQuery($identifier, $sharedChannel)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The tenant-scoped suppression still refusing this identifier, if any.
     *
     * ⚠️ **ONE PREDICATE — AND IT HAS THREE CALLERS NOW, WHICH IS THE ONLY
     * REASON IT IS A METHOD.** `isSuppressed()` asks whether anything refuses;
     * the tenant CRM's Never-contact control (`34` §1.2, `App\Services\Crm\
     * NeverContact`) asks *which* refusal is standing, because the owner may
     * withdraw their own instruction and must never be able to clear a STOP the
     * customer sent; and `badgesFor()` asks the same question for a page of
     * contacts at once through `standingPredicate()`, which is this predicate
     * applied to a passed builder rather than a second spelling of it. Two
     * implementations of one rule is `decide()`/`permit()`'s stated hazard, and
     * a lift-generation predicate that drifted between the badge and the gate
     * would show a contact as reachable that a send refuses.
     *
     * ⚠️ **IT ANSWERS FOR THE TENANT LIST ONLY, DELIBERATELY.** A platform-scoped
     * opt-out is not a tenant's to see the internals of and not a tenant's to
     * lift — `hasOptedOut()` is asked first by `isSuppressed()` for that reason,
     * and a caller reading this method alone gets null while the send is still
     * refused. Nothing may treat a null here as permission; ask `decide()`.
     *
     * @return Builder<SuppressionListEntry>
     */
    private function standingEntryQuery(string $identifier, OutreachChannel $channel): Builder
    {
        return $this->standingPredicate(SuppressionListEntry::query(), $identifier, $channel);
    }

    /**
     * Apply the standing-suppression predicate for one (identifier, channel)
     * pair to a builder — the one spelling of "does this row still refuse",
     * composed by `standingEntryQuery()` for a single pair and by `badgesFor()`
     * into an OR group for a page of them.
     *
     * @param  Builder<SuppressionListEntry>  $query
     * @return Builder<SuppressionListEntry>
     */
    private function standingPredicate(Builder $query, string $identifier, OutreachChannel $channel): Builder
    {
        $hash = Identifier::hash($identifier, $channel);

        return $query
            ->where('channel', $channel)
            ->where('identifier', $identifier)
            // ⚠️ A LIFTED ENTRY IS STILL HERE, AND THAT IS THE DESIGN. Nothing
            // deletes a suppression row; the reversal is its own row in
            // `suppression_lifts`, so the account of "they stopped, then they
            // started again" survives both events. What changes is whether this
            // one still refuses.
            //
            // ⚠️ A NULL HASH LEAVES THE ENTRY LIVE FOREVER, WHICH IS THE SAFE
            // DIRECTION. An identifier that will not normalise cannot be matched
            // against a lift's stored hash, so it can never be shown to have
            // been lifted — and `hasOptedOut()` above has already failed closed
            // on exactly the same input for exactly the same reason.
            ->when(
                $hash !== null,
                fn (Builder $query) => $query->whereNotExists(
                    $this->liftsClearing($channel, $hash ?? '', 'suppression_list.lift_generation')
                        // A platform lift releases the person everywhere, so it
                        // clears a tenant's own entry too. A tenant lift clears
                        // only that tenant's — which is this table's whole
                        // boundary, and the reason the predicate is written here
                        // rather than left to a global scope the lift table
                        // deliberately does not have.
                        ->where(function (Builder $reach): void {
                            $reach->where('suppression_lifts.scope', OptOutScope::Platform)
                                ->orWhereColumn('suppression_lifts.business_id', 'suppression_list.business_id');
                        }),
                ),
            );
    }

    /**
     * The standing tenant-scoped suppression on a contact's channel, if any.
     *
     * Read-only, and the public face of `standingEntryQuery()` — whose docblock
     * carries the two warnings a caller needs, in particular that a null here
     * does not mean the send is permitted.
     */
    public function standingSuppression(Customer $customer, OutreachChannel $channel): ?SuppressionListEntry
    {
        $this->assertBelongsToTenant($customer);

        $identifier = $this->identifierFor($customer, $channel);

        if ($identifier === null) {
            return null;
        }

        return $this->standingEntryQuery($identifier, $channel)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The lifts that would clear a suppression row of the given generation.
     *
     * ⚠️ `generation` IS COMPARED WITH `=`, NEVER `>=`, AND THE DIFFERENCE IS THE
     * WHOLE MECHANISM. A lift clears the one refusal it was issued against.
     * Written as a comparison it would pre-clear every future STOP for that
     * identifier: somebody who said STOP, sent START, and said STOP again would
     * be silently sendable, and the second STOP would have been accepted, audited
     * and had no effect. That is the failure `opt_outs` exists to prevent,
     * rebuilt inside the feature meant to complete it.
     *
     * @param  string  $generationColumn  the suppression table's own generation column
     * @return Builder<SuppressionLift>
     */
    private function liftsClearing(OutreachChannel $channel, string $hash, string $generationColumn): Builder
    {
        return SuppressionLift::query()
            ->where('identifier_type', $channel)
            ->where('value_hash', $hash)
            ->whereColumn('suppression_lifts.generation', '=', $generationColumn);
    }

    /**
     * The next generation for this identifier and channel, across every scope.
     *
     * ⚠️ ONE SEQUENCE FOR BOTH SUPPRESSION STORES, AND THE FIRST ATTEMPT AT THIS
     * USED TWO. A per-store counter drifts the moment a lift reaches one store
     * and not the other — a tenant-scoped lift advances the tenant list's count
     * while leaving the platform register's untouched, and from then on the two
     * rows are numbered differently, so the next lift can only ever clear one of
     * them. The store left behind stays suppressed forever, invisibly, because
     * nothing about it looks wrong. One sequence per identifier removes the
     * possibility rather than managing it.
     *
     * `max + 1` rather than a count, because two tenants may legitimately lift at
     * the same generation — the unique key admits one row per scope — and a count
     * would then hand the next STOP a generation a lift already occupies, which
     * would make it born already cleared.
     */
    private function nextGeneration(OutreachChannel $channel, ?string $hash): int
    {
        if ($hash === null) {
            return 0;
        }

        $highest = SuppressionLift::query()
            ->where('identifier_type', $channel)
            ->where('value_hash', $hash)
            ->max('generation');

        return $highest === null ? 0 : ((int) $highest) + 1;
    }

    /**
     * The platform-scoped half, and the one this service used to be wrong about.
     *
     * ⚠️ THE EXTRA PREDICATE THIS CLASS'S OWN DOCBLOCK PREDICTED (decision 294).
     * `suppression_list` is tenant-owned, which is the right boundary on Lane B
     * — each tenant has its own number, so a STOP to the dentist says nothing
     * about the mechanic. **Lane A is one shared toll-free number**, and a
     * carrier STOP is keyed on the sending number and the recipient, so the
     * first STOP ends that number's right to text that person and every tenant
     * on Lane A sends from it.
     *
     * ⚠️ ASKED FIRST, BEFORE THE TENANT-SCOPED LIST, and the order is not an
     * optimisation. `SuppressionListEntry` is tenant-owned, so its query needs a
     * resolved tenant; this one deliberately does not, because the whole point
     * is a refusal that outlives whichever tenant is asking. Putting it second
     * would make the platform boundary depend on the tenant boundary having
     * resolved first.
     *
     * ⚠️ NULL FROM `hash()` MEANS NO SEND, NOT NO OPT-OUT. An identifier that
     * cannot be normalised cannot be compared against a stored hash either — so
     * treating it as "not opted out" would make an unparseable number
     * permanently un-suppressible while looking perfectly sendable. It fails
     * closed instead.
     */
    private function hasOptedOut(string $identifier, OutreachChannel $channel): bool
    {
        $hash = Identifier::hash($identifier, $channel);

        if ($hash === null) {
            return true;
        }

        return OptOut::query()
            ->where('identifier_type', $channel)
            ->where('value_hash', $hash)
            ->where(function (Builder $query): void {
                // Platform rows bind everybody. Tenant rows bind only the tenant
                // asking — and `Tenancy::id()` rather than `idOrFail()` because
                // a platform-scoped refusal must be answerable on a path where
                // no tenant is resolved at all.
                $query->where('scope', OptOutScope::Platform)
                    ->orWhere(function (Builder $tenant): void {
                        $tenant->where('scope', OptOutScope::Tenant)
                            ->where('business_id', Tenancy::id());
                    });
            })
            // ⚠️ THE REFUSAL IS STILL IN THE TABLE; THIS ASKS WHETHER IT STILL
            // STANDS. `opt_outs` is append-only in the model and nothing may
            // delete a row, so a released identifier is a refusal with a
            // matching lift beside it rather than an absence — which is what
            // makes "when did we start messaging this person again, and who
            // said we could" a question with an answer.
            //
            // ⚠️ THE LIFT MUST MATCH THE REFUSAL'S OWN SCOPE, not merely its
            // identifier. A tenant-scoped lift cannot clear the platform
            // register: tenant B deciding one person is reachable again says
            // nothing about the shared toll-free number that person sent STOP
            // to, and treating it as though it did would rebuild decision 294
            // pointing the other way.
            ->whereNotExists(
                $this->liftsClearing($channel, $hash, 'opt_outs.lift_generation')
                    ->whereColumn('suppression_lifts.scope', 'opt_outs.scope')
                    ->whereRaw('suppression_lifts.business_id IS NOT DISTINCT FROM opt_outs.business_id'),
            )
            ->exists();
    }

    /**
     * Write the consent event and refresh what is derived from it, atomically.
     *
     * DATA-MODEL §5.6 says these columns are "refreshed by jobs". A job opens a
     * window in which the consent record exists and sms_consent still reads
     * false — and that window is exactly when a send decision is most likely to
     * be wrong. Refreshing inside the same transaction closes it; the
     * load-bearing half of the original rule, "never set by hand", survives
     * unchanged in $guarded.
     */
    public function record(
        Customer $customer,
        OutreachChannel $channel,
        ConsentCapture $capture,
        string $actor,
    ): ConsentRecord {
        // ⚠️ SUPPORT CANNOT AUTHOR CONSENT, AND THIS IS THE LINE THAT SAYS SO.
        // An agent holding a live act-as session who opens a tenant's own
        // feedback page and submits it would otherwise write a consent record
        // in a real customer's name — manufactured evidence in the one table
        // whose entire purpose is to be evidence, on a path that needs no
        // authentication and therefore no separate authorisation bug.
        //
        // `28` §9.4's blocklist does not name it; the blocklist predates
        // consent records being this platform's TCPA proof. Inert for every
        // ordinary caller, which is all of them: the public form, the wizard,
        // and row 4's importers run with no session open and never reach the
        // throw.
        //
        // ⚠️ withdraw() and suppress() are deliberately NOT guarded. Every
        // blocked capability creates authority; those two destroy it, and an
        // agent taking a call from somebody saying "stop texting me" has to be
        // able to act on it in that minute. See ImpersonationCapability.
        $this->impersonation->refuse(ImpersonationCapability::RecordConsent);

        $this->assertBelongsToTenant($customer);

        return DB::transaction(function () use ($customer, $channel, $capture, $actor): ConsentRecord {
            $record = ConsentRecord::create([
                'customer_id' => $customer->id,
                'channel' => $channel,
                'consent_type' => $capture->consentType,
                'captured_by' => $capture->capturedBy,
                'capture_surface' => $capture->captureSurface,
                'disclosure_version' => $capture->disclosureVersion,
                'method' => $capture->method,
                'proof' => $capture->proof,
            ]);

            $this->refreshDerived($customer);

            // `29` §2 rule 42: every sensitive action reaches the append-only
            // log. consent_records is itself append-only and self-auditing, so
            // this is not about what was captured — it is about who captured it.
            // The record says a person consented; only this says which operator,
            // job or public request put it there. NO PROOF BLOB and no
            // identifier travels into the metadata: audit_log is read by more
            // people than consent_records is, and the proof is one lookup away
            // through the entity reference.
            $this->audit->record('consent.recorded', $actor, $record, [
                'customer_id' => $customer->id,
                'channel' => $channel->value,
                'captured_by' => $capture->capturedBy->value,
                'capture_surface' => $capture->captureSurface->value,
                'consent_type' => $capture->consentType->value,
                'disclosure_version' => $capture->disclosureVersion,
            ]);

            return $record;
        });
    }

    /**
     * Refuse to write consent for somebody else's customer.
     *
     * The read path is already scoped, so a stray customer grants no permit and
     * leaks nothing. The write path had no such protection: `business_id` comes
     * from the ambient tenant while `customer_id` comes from the passed model,
     * and consent_records carries plain single-column foreign keys whose
     * integrity checks bypass row security. So tenant A could write consent
     * evidence naming tenant B's customer — a row that would then appear in
     * tenant A's compliance export as consent from a person tenant A has never
     * met, and whose derived-column refresh RLS would silently discard.
     *
     * This is the *wrong tenant* case CLAUDE.md warns RLS cannot catch, so it is
     * checked in the application layer, where the two ids are both in hand.
     */
    private function assertBelongsToTenant(Customer $customer): void
    {
        if ($customer->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That customer belongs to another tenant. Consent is written against the '
            .'acting business, so this would file evidence under a business that never '
            .'captured it.',
        );
    }

    /**
     * Recompute the cache columns on customers from consent_records.
     *
     * These are assigned directly rather than through fill(): they are in
     * $guarded precisely so that nothing else can, and this service is the
     * "else" the guard exempts.
     *
     * ⛔ **THAT SENTENCE OVERSTATES WHAT $guarded DOES, AND THE SHAPE IT
     * OVERSTATES IS THIS METHOD'S OWN** (9141). Mass assignment is applied by
     * fill(), so $guarded refuses create(), fill() and $model->update() and
     * refuses nothing else: a second service writing
     * `$customer->messaging_lane = …` — four lines of this method, copied — is
     * not mass assignment and meets no guard at all, and neither is
     * `Customer::query()->update(['messaging_lane' => …])`, forceFill() or a
     * raw UPDATE. **What holds these four columns to this one method is the
     * chokepoint lint in tests/Feature/Architecture/ConsentTest.php**, which
     * asks all eight write shapes over app/, database/ and routes/ and names
     * this file and this method by name. The reading kept above is the reason
     * the columns are guarded; it was never the reason nothing else writes
     * them.
     *
     * THE LANE IS NOT A PERMISSION. It says which number would send, not whether
     * we may send — suppression is not consulted here, only at permit(). Audience
     * queries that filter on messaging_lane must still pass every candidate
     * through permit() before sending. Row 4: this means you.
     *
     * NEITHER ARE sms_consent AND email_consent PERMISSIONS. They mean "a
     * consent record exists for this channel", never "may be texted" —
     * suppression is deliberately excluded from this method entirely, so a
     * customer who sent STOP keeps sms_consent = true forever. That is
     * consent_records staying append-only, and it is exactly why permit()
     * checks suppression itself rather than trusting these columns. Any
     * audience filter built on either boolean must still pass every candidate
     * through permit() before a send, exactly like the lane.
     *
     * `consent_source` deliberately spans every channel — "where did this
     * customer's newest consent event happen" is a support question, not a
     * routing one — while the lane is scoped to the SMS family, because only
     * the SMS family has a phone number to route through. Both winners are
     * picked by newest(), the same ordering rule, so the rule cannot drift
     * between them the way a per-channel loop and a separate cross-channel `id`
     * comparison once did here: an SMS/Platform row at a lower id lost to a
     * null-dated Whatsapp/Tenant row at a higher id, because the two orderings
     * disagreed (caught in review, before this shipped).
     */
    private function refreshDerived(Customer $customer): void
    {
        // WRITTEN TO A ROW THIS METHOD FETCHED, NOT TO THE CALLER'S INSTANCE.
        // $customer->save() flushes every dirty attribute on the object it is
        // handed, so a caller who had done `$customer->name = $input` before
        // calling record() would have that edit committed inside the consent
        // transaction — and rolled back with it. The four derived columns are
        // this service's to write; nothing else on that model is.
        //
        // lockForUpdate() because the read-then-write is not atomic on its own:
        // two concurrent record() calls for one customer would each recompute
        // from a set missing the other's uncommitted row, and the later commit
        // would win with the older answer. That answer is messaging_lane, which
        // is to say which number sends.
        $target = Customer::query()->lockForUpdate()->findOrFail($customer->id);

        $target->sms_consent = ConsentRecord::query()
            ->where('customer_id', $customer->id)
            ->where('channel', OutreachChannel::Sms)
            ->exists();

        $target->email_consent = ConsentRecord::query()
            ->where('customer_id', $customer->id)
            ->where('channel', OutreachChannel::Email)
            ->exists();

        $newestSmsFamily = $this->newest($customer, self::SMS_FAMILY);
        $newestOverall = $this->newest($customer);

        $target->messaging_lane = $newestSmsFamily?->captured_by->lane() ?? MessagingLane::None;
        $target->consent_source = $newestOverall?->capture_surface->value;

        $target->save();
    }

    /**
     * The newest consent_records row for a customer, optionally restricted to
     * a set of channels — one ordering rule, expressed once, shared by both of
     * refreshDerived()'s winners so they cannot disagree with each other.
     *
     * Postgres sorts NULL first on a DESC ORDER BY, so a plain
     * ->latest('created_at') would let a null-dated row outrank every dated one
     * before the ->latest('id') tiebreak ever ran — and here that decides which
     * phone number a text goes out on, or whose consent event is on record. Do
     * not simplify this back.
     *
     * @param  list<OutreachChannel>|null  $channels
     */
    private function newest(Customer $customer, ?array $channels = null): ?ConsentRecord
    {
        $query = ConsentRecord::query()->where('customer_id', $customer->id);

        if ($channels !== null) {
            $query->whereIn('channel', $channels);
        }

        return $query
            ->orderByRaw('created_at DESC NULLS LAST')
            ->latest('id')
            ->first();
    }

    /**
     * This contact, on this channel, stops. Refreshes the derived cache after.
     *
     * A convenience over suppress() for the case where a Customer is in hand.
     * The suppression itself is still written against the identifier, because
     * that is the only key that survives duplicate customer rows.
     *
     * ⚠️ `$scope` PASSES THROUGH TO `suppress()` AND KEEPS ITS FAIL-SAFE DEFAULT.
     * Platform is the safe direction for a STOP whose sending number we do not
     * know — over-suppressing costs a message, under-suppressing costs a message
     * to somebody who refused. It is *not* the safe direction for an instruction
     * the tenant gave about their own list: an owner marking a contact
     * Never-contact (`34` §1.2) is speaking for their own business, and writing
     * that at platform scope would let one tenant mute a person for every other
     * tenant on the shared number. That caller passes `Tenant` explicitly and
     * `SuppressionReason::NeverContact` says on the row whose instruction it was.
     */
    public function withdraw(
        Customer $customer,
        OutreachChannel $channel,
        SuppressionReason $class,
        string $reason,
        string $actor,
        OptOutScope $scope = OptOutScope::Platform,
    ): void {
        $this->assertBelongsToTenant($customer);

        $identifier = $this->identifierFor($customer, $channel);

        if ($identifier === null) {
            return;
        }

        DB::transaction(function () use ($customer, $channel, $class, $identifier, $reason, $actor, $scope): void {
            $this->suppress($identifier, $channel, $class, $reason, $actor, $scope);
            $this->refreshDerived($customer);
        });
    }

    /**
     * A STOP that arrived from a carrier, where there is no tenant to be had.
     *
     * ⚠️ **THIS IS A SECOND PUBLIC SUPPRESSION METHOD AND `registerOptOut()`'s
     * DOCBLOCK ARGUES AGAINST EXACTLY THAT** — *"the alternative is a caller
     * that remembers one and forgets the other"*. It is added anyway, and the
     * reason the hazard does not apply is the direction: that warning is about
     * a caller writing the tenant list and **forgetting the platform register**,
     * which is the weaker of the two. This method writes only the platform
     * register, which is the **stronger** one — it refuses every send to that
     * person on every tenant, because `isSuppressed()` asks `hasOptedOut()`
     * before it asks anything tenant-scoped. A caller who reaches for this by
     * mistake over-suppresses. A caller who reaches for `suppress()` by mistake
     * on this path gets an exception, not a silent half-write.
     *
     * ⚠️ **AND IT IS NOT A CONVENIENCE — `suppress()` CANNOT RUN HERE.** That
     * method writes `suppression_list`, which is tenant-owned, and audits
     * through `AuditService::record()`, which opens with `Tenancy::idOrFail()`.
     * An inbound carrier webhook carries a sender, our receiving number and a
     * body, and **nothing in it names a business**: one shared Lane A number,
     * and no map from a number to a tenant until slice 6's `numbers` table.
     * Establishing an arbitrary tenant to satisfy the signature would file a
     * customer's refusal against a business that had nothing to do with it.
     *
     * ⚠️ **PLATFORM SCOPE IS NOT A FALLBACK, IT IS THE CORRECT READING.** A
     * carrier STOP is keyed on the sending number and the recipient, not on
     * whichever tenant prompted the message — so on a shared number the first
     * STOP ends that number's right to text that person entirely.
     * `registerOptOut()` said so before this method existed.
     *
     * Returns false when the identifier will not normalise (425): a hash matches
     * only exactly, so a row written against an unmatchable value would refuse
     * nothing while reporting success.
     */
    public function suppressFromCarrier(
        string $identifier,
        OutreachChannel $channel,
        SuppressionReason $class,
        string $actor,
    ): bool {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new InvalidArgumentException(
                'Cannot suppress an empty identifier. A STOP with nothing to suppress is a '
                .'dropped STOP, which is the one message that must never be dropped.',
            );
        }

        $hash = Identifier::hash($identifier, $channel);

        if ($hash === null) {
            // Fails closed, and the caller has already refused for the same
            // reason — this is the second of the two guards on one fact, kept
            // because a hash that cannot be computed here would otherwise be
            // written as an empty string and match every future lookup for a
            // number nobody can name.
            return false;
        }

        // ⛔ **THE HASH AND THE EPOCH ARE ONE TRANSACTION, AND THAT IS THE
        // WHOLE OF 8200.** They used to be two statements eleven lines apart
        // with no transaction anywhere in this method, so an ordinary crash, a
        // webhook timeout or a deploy restart between them left a durable hash
        // with no live epoch — `status()` `Unattributed`, `isReadable()` false,
        // and `decide()` refusing **every send on the platform** with no key
        // having changed and no way out but a person running
        // `consent:hash-epoch --adopt`. Reproduced before it was fixed, at this
        // site and at `SuppressionRegistry::load()`, in
        // `ConsentKeyRotationTest`.
        //
        // ⚠️ **BOTH DIRECTIONS ARE BAD AND THE TRANSACTION IS WHY NEITHER IS
        // REACHABLE.** A hash with no epoch bricks the platform; an epoch with
        // no hash asserts a key wrote something it never wrote. Ordering alone
        // fixes only the second.
        //
        // ⛔ **THE EPOCH IS RECORDED HERE BECAUSE THIS IS THE WRITE WITH NO
        // CLEAR COPY** (8080). The row below is a keyed hash and the `Log::info`
        // further down deliberately omits the identifier, so after an `APP_KEY`
        // rotation this STOP is unmatchable and unrecoverable from anything in
        // this schema. {@see IdentifierHashEpochs} is what makes that
        // detectable; without this line it stays exactly as silent as it was.
        DB::transaction(function () use ($channel, $hash, $class): void {
            OptOut::query()->firstOrCreate([
                'scope' => OptOutScope::Platform,
                'business_id' => null,
                'identifier_type' => $channel,
                'value_hash' => $hash,
                'lift_generation' => $this->nextGeneration($channel, $hash),
            ], ['created_at' => now(), 'reason_class' => $class]);

            $this->epochs->observe('carrier-stop');
        });

        // ⚠️ THE LOG RATHER THAN `audit_log`, FOR THE REASON THIS METHOD EXISTS.
        // `AuditService::record()` opens with `Tenancy::idOrFail()`. Nothing is
        // silently dropped; the identifier is deliberately absent, because a
        // platform-wide log of numbers that opted out is the marketing list
        // `opt_outs` stores a hash precisely to avoid being.
        Log::info('A carrier suppression was registered platform-wide.', [
            'channel' => $channel->value,
            'reason_class' => $class->value,
            'actor' => $actor,
        ]);

        return true;
    }

    /**
     * A START that arrived from a carrier, where there is no tenant to be had.
     *
     * ⚠️ **THE COUNTERPART TO `suppressFromCarrier()`, AND IT EXISTS FOR THE
     * SAME UNAVOIDABLE REASON RATHER THAN FOR SYMMETRY.** `lift()` reads
     * `suppression_list` — tenant-owned — and audits through `AuditService`,
     * which opens with `Tenancy::idOrFail()`. Neither is available on an inbound
     * webhook, so calling it here does not merely do the wrong thing: it throws.
     *
     * ⚠️ **CARRIER RULES OBLIGE US TO HONOUR THIS, WHICH IS WHY IT IS NOT
     * SIMPLER TO OMIT.** STOP and START are one rulebook. A product that
     * honoured only the first would be refusing to let somebody back in who
     * asked, and the refusal would be invisible to them — they would text START,
     * receive nothing, and have no way to tell whether it worked.
     *
     * ⚠️ **IT LIFTS THROUGH `suppression_lifts` AND DELETES NOTHING.** The
     * reversal is a row beside the refusal rather than in place of it, matched
     * on the refusal's own generation — so STOP → START → STOP is three
     * permanent facts, and the second STOP is *not* pre-cleared by the earlier
     * lift. `liftsClearing()` documents why that comparison is `=` and never
     * `>=`; this method is the path that makes the distinction reachable from
     * outside.
     *
     * ⚠️ **A NON-LIFTABLE CLASS IS REFUSED, AND SILENTLY.** A complaint or a
     * litigator listing is not clearable by texting START, and answering the
     * sender differently would tell them which register they are on.
     *
     * Returns false when there was nothing to release, which is an ordinary
     * message rather than a failure — somebody texting START who never opted out
     * is just somebody texting.
     */
    public function liftFromCarrier(
        string $identifier,
        OutreachChannel $channel,
        LiftSource $source,
        string $actor,
    ): bool {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new InvalidArgumentException(
                'Cannot lift an empty identifier. A START with nothing to release is a caller '
                .'bug, not a no-op, and swallowing it would hide the identifier never arriving.',
            );
        }

        $hash = Identifier::hash($identifier, $channel);

        if ($hash === null) {
            // Fails closed, exactly as `lift()` does on the same input: a lift
            // written against an unmatchable hash releases nothing while
            // reporting success.
            return false;
        }

        return DB::transaction(function () use ($channel, $hash, $source, $actor): bool {
            $optOut = OptOut::query()
                ->where('identifier_type', $channel)
                ->where('value_hash', $hash)
                ->where('scope', OptOutScope::Platform)
                ->whereNull('business_id')
                // NULLS LAST said out loud even though the column is NOT NULL,
                // for the reason `lift()` states at the same query.
                ->orderByRaw('lift_generation DESC NULLS LAST')
                ->first();

            if (! $optOut instanceof OptOut) {
                return false;
            }

            if (! $optOut->reason_class->isLiftable()) {
                return false;
            }

            // firstOrCreate, because a retried START is as ordinary as a retried
            // STOP and the unique key would otherwise raise on the second one.
            SuppressionLift::query()->firstOrCreate([
                'scope' => OptOutScope::Platform,
                'business_id' => null,
                'identifier_type' => $channel,
                'value_hash' => $hash,
                // ⚠️ THE REFUSAL'S OWN GENERATION, NEVER THE NEXT ONE. A lift
                // clears the one refusal it was issued against; written against
                // a later generation it would pre-clear a STOP that has not
                // happened yet.
                'generation' => $optOut->lift_generation,
            ], [
                'source' => $source,
                'actor' => $actor,
                'created_at' => now(),
            ]);

            // The log rather than `audit_log`, for this method's whole reason.
            // The identifier is absent: a platform-wide log of numbers that
            // opted back in is the same marketing list `opt_outs` stores a hash
            // to avoid being.
            Log::info('A carrier suppression was lifted platform-wide.', [
                'channel' => $channel->value,
                'source' => $source->value,
                'actor' => $actor,
            ]);

            return true;
        });
    }

    /**
     * Suppress an identifier with no Customer in hand.
     *
     * This is the shape a STOP actually arrives in: a webhook carrying a phone
     * number and nothing else. Requiring a Customer would mean resolving one
     * first, and a resolution that fails would mean dropping a STOP — the one
     * message that must never be dropped.
     *
     * Idempotent by construction: a second STOP on an already-suppressed
     * identifier is the ordinary case, not an error the caller has to guard
     * against, so this reaches for firstOrCreate() rather than create() against
     * the table's UNIQUE(business_id, channel, identifier).
     *
     * Trimmed here as well as in identifierFor(), and that is not the same thing
     * as the normalisation deferred below. The read side already trims, so a
     * suppression stored with surrounding whitespace would be a row permit() can
     * never match — a STOP that was accepted, written, and silently has no
     * effect. Trimming on both sides is what makes them the same key.
     *
     * Known limitation, ruled out of scope for this slice: beyond whitespace,
     * identifiers are matched exactly as stored. A number held here as
     * `+15550001111` and arriving from a carrier webhook as `15550001111` would
     * miss, and the same goes for `Foo@example.com` against `foo@example.com`.
     * Every write and read inside this application goes through identifierFor(),
     * so they agree by construction — the mismatch only becomes reachable once
     * an identifier arrives from outside, which is row 4's inbound work.
     * Normalisation belongs at that boundary, applied once, to both sides, not
     * scattered across every caller here.
     *
     * ✅ THERE IS A WAY BACK NOW, AND IT IS `lift()` — this paragraph used to say
     * there was not. Carrier rules require honouring START as well as STOP, and
     * that resume path goes through this service and writes an audit row, exactly
     * as this docblock asked for. What it does *not* do is delete anything:
     * `suppression_lifts` records the reversal beside the refusal rather than in
     * place of it, so both events survive.
     *
     * ⚠️ `$class` IS REQUIRED AND HAS NO DEFAULT, WHICH BREAKS EVERY CALLER ON
     * PURPOSE. The safe-looking default is `Stop`, and it is the permissive one:
     * `Stop` is the only class that lifts, so a bounce or a complaint arriving
     * through a caller that did not think about it would become clearable by a
     * START. 486's rule — a parameter whose default is the wrong answer for the
     * cases that have not been built yet is a parameter that must be passed.
     */
    public function suppress(
        string $identifier,
        OutreachChannel $channel,
        SuppressionReason $class,
        string $reason,
        string $actor,
        OptOutScope $scope = OptOutScope::Platform,
    ): void {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new InvalidArgumentException(
                'Cannot suppress an empty identifier. A STOP with nothing to suppress is a '
                .'dropped STOP, which is the one message that must never be dropped.',
            );
        }

        $generation = $this->nextGeneration($channel, Identifier::hash($identifier, $channel));

        // ⚠️ THE GENERATION IS PART OF THE MATCH, NOT PART OF THE PAYLOAD, AND
        // THAT IS WHAT MAKES A SECOND STOP LAND. Left in the payload, a retried
        // webhook stays idempotent and a genuine re-STOP after a lift finds the
        // old cleared row, writes nothing, and is silently discarded. In the
        // match, a retry computes the same generation and no-ops while a re-STOP
        // computes the next one and inserts.
        $entry = SuppressionListEntry::query()->firstOrCreate(
            ['channel' => $channel, 'identifier' => $identifier, 'lift_generation' => $generation],
            ['reason' => $reason, 'reason_class' => $class],
        );

        // Only the first one is audited. A repeat STOP on an already-suppressed
        // identifier is the ordinary case rather than an event, and the thing
        // this log exists to survive — a loop, a retried webhook — is exactly
        // what would otherwise write a row per iteration.
        //
        // ⛔ THE IDENTIFIER NO LONGER TRAVELS, AND IT DID UNTIL 2026-08-22
        // (8046). This paragraph used to end: *"the identifier is in the
        // metadata because a suppression is keyed on it and cannot be
        // investigated without it; that is a deliberate exception to keeping
        // contact details out of audit_log."* The exception was real and it was
        // argued — and 8043 then ruled that `audit_log` is kept **for ever** and
        // may never acquire a pruner, which turned it into an end customer's
        // phone number or email address held in clear, permanently, in the table
        // more people read than any other.
        //
        // ⚠️ THE ROUTE IS THE ENTITY REFERENCE AND IT IS STRICTLY BETTER THAN A
        // COPY. This row names the `suppression_list` entry, which holds the
        // identifier as the authoritative copy, cascades on the same
        // `business_id` as `audit_log` does — identical lifetimes — and is
        // append-only at the model layer for that reason. A copy in the metadata
        // could disagree with it; a pointer cannot.
        if ($entry->wasRecentlyCreated) {
            $this->audit->record('consent.withdrawn', $actor, $entry, [
                'channel' => $channel->value,
                'reason' => $reason,
                // The class rather than only the free text, because the free
                // text is whatever a carrier or a vendor happened to send and
                // the class is what decides whether this is ever reversible.
                'reason_class' => $class->value,
                'generation' => $generation,
            ]);
        }

        $this->registerOptOut($identifier, $channel, $class, $scope, $actor, $generation);
    }

    /**
     * Record the refusal at the scope the sending number actually implies.
     *
     * ⚠️ WRITTEN BY `suppress()` RATHER THAN BY A SECOND PUBLIC METHOD, because
     * the alternative is a caller that remembers one and forgets the other —
     * and the one they would forget is this one, since the tenant-scoped list is
     * the one that already existed and already appeared to work. A STOP reaches
     * exactly one entry point, and both records are written from it.
     *
     * ⚠️ `Platform` IS THE DEFAULT SCOPE, and that is the fail-safe direction.
     * Lane A's shared number is what a STOP arrives on when we do not know
     * better, and over-suppressing costs a message that was not sent while
     * under-suppressing costs a message to somebody who said STOP. Row 4's
     * inbound webhook knows which number carried the STOP and passes the scope
     * explicitly; until it exists, nothing may quietly assume the narrower one.
     *
     * `firstOrCreate` rather than `create`: a retried webhook is the ordinary
     * case, the unique index would raise on the second attempt, and a raised
     * exception on a STOP path is a dropped STOP.
     */
    private function registerOptOut(
        string $identifier,
        OutreachChannel $channel,
        SuppressionReason $class,
        OptOutScope $scope,
        string $actor,
        int $generation,
    ): void {
        $hash = Identifier::hash($identifier, $channel);

        if ($hash === null) {
            // ⚠️ NOT SILENT, AND NOT FATAL. The tenant-scoped entry above was
            // written against the raw identifier and still refuses this exact
            // string, so the STOP is honoured for the tenant that received it.
            // What cannot be written is the cross-tenant half, because an
            // identifier that will not normalise cannot be compared to a hash.
            // Throwing here would undo the suppression that did work.
            $this->audit->record('consent.opt_out_not_registered', $actor, null, [
                'channel' => $channel->value,
                'reason' => 'identifier_not_normalisable',
            ]);

            return;
        }

        // ⛔ **ONE TRANSACTION, FOR 8200's REASON AND NOT FOR THIS METHOD'S.**
        // This is the third site of the shape, and the scout who found the
        // other two did not name it: the durable hash and the epoch that says
        // which key wrote it were two statements with nothing joining them, so
        // an interruption between them left the platform refusing every send
        // until a person ran `consent:hash-epoch --adopt`. It is **narrower
        // than `suppress()` deliberately** — the `suppression_list` entry above
        // is matched in clear and still refuses this tenant, and the comment on
        // the un-normalisable arm above already argues that a failure here must
        // not undo the suppression that did work.
        //
        // ⚠️ **IT NESTS**, and that is the point rather than a tolerance:
        // `withdraw()` already wraps this in a transaction of its own, so this
        // one is a savepoint there and the whole thing stays atomic either way.
        //
        // The generation joins the match here for the same reason it does in the
        // tenant list above: without it, a STOP arriving after a lift finds the
        // cleared row and writes nothing.
        //
        // The epoch is the platform half of a tenant-recorded withdrawal, and
        // the same keyed-hash write as `suppressFromCarrier()`'s — so the same
        // epoch.
        // ⚠️ A LIFT IS DELIBERATELY NOT INSTRUMENTED: `suppression_lifts` is
        // written only against a hash that is already in `opt_outs`, so an epoch
        // is necessarily already recorded and a second call here would only
        // widen the surface a reader has to check.
        DB::transaction(function () use ($scope, $channel, $hash, $generation, $class): void {
            OptOut::query()->firstOrCreate([
                'scope' => $scope,
                'business_id' => $scope->requiresBusiness() ? Tenancy::idOrFail() : null,
                'identifier_type' => $channel,
                'value_hash' => $hash,
                'lift_generation' => $generation,
            ], ['created_at' => now(), 'reason_class' => $class]);

            $this->epochs->observe('suppression');
        });
    }

    /**
     * Release an identifier that was suppressed, on an authority that can be named.
     *
     * ⚠️ THIS IS THE METHOD `suppress()` HAS BEEN ASKING FOR SINCE IT WAS WRITTEN,
     * and the shape is the one that docblock specified: through this service, and
     * writing an audit row, "because deleting a suppression_list row directly
     * reverses a withdrawal with no record that anybody did it".
     *
     * ⚠️ THERE IS NO CONSENT-CAPTURE PATH INTO HERE, AND `LiftSource` HAS NO CASE
     * FOR ONE. `OptOut`'s docblock used to promise that a new consent record
     * re-granted permission; `decide()` has never worked that way, checking STOP
     * at step 2 and the consent record at step 4 (1020). Keeping it that way is
     * decisions 334–337: `/f/{slug}` is public and unauthenticated and has
     * **already** been found writing consent against identifiers the submitter did
     * not own, so a capture that lifted a suppression would let anybody who knows
     * a suppressed person's email un-suppress them by filling in a form.
     *
     * ⚠️ IT LIFTS WHAT IS LIVE AND NOTHING ELSE. A lift written against an
     * identifier with no standing suppression would sit at a generation a future
     * STOP is about to be born into — that STOP would be cleared before it was
     * ever made. So a call with nothing to release writes no row and returns
     * false, and that is not an error: a START from somebody who never opted out
     * is an ordinary message.
     *
     * ⚠️ THE REASON CLASS DECIDES, NOT THE CALLER. A complaint never lifts and a
     * bounce cannot lift until there is a delivery feed to verify one — see
     * `SuppressionReason`. `$force` does not exist and must not be added: the two
     * refusals are the whole point of classing suppressions in the first place.
     *
     * @return bool whether anything was actually released
     */
    public function lift(
        string $identifier,
        OutreachChannel $channel,
        LiftSource $source,
        string $actor,
        OptOutScope $scope = OptOutScope::Platform,
        ?string $note = null,
    ): bool {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new InvalidArgumentException(
                'Cannot lift an empty identifier. A START with nothing to release is a caller '
                .'bug, not a no-op, and swallowing it would hide the identifier never arriving.',
            );
        }

        $hash = Identifier::hash($identifier, $channel);

        if ($hash === null) {
            // ⚠️ FAILS CLOSED, THE SAME AS `hasOptedOut()` ON THE SAME INPUT. A
            // hash only matches exactly, so an identifier that will not normalise
            // cannot be shown to match the suppression it claims to clear — and a
            // lift written against an unmatchable hash would release nothing
            // while reporting success.
            return false;
        }

        return DB::transaction(function () use ($identifier, $channel, $hash, $source, $actor, $scope, $note): bool {
            $entry = SuppressionListEntry::query()
                ->where('channel', $channel)
                ->where('identifier', $identifier)
                // NULLS LAST said out loud even though the column is NOT NULL —
                // the convention lint is right to insist, and a default that
                // becomes nullable later would otherwise put an undated row
                // ahead of every live one silently.
                ->orderByRaw('lift_generation DESC NULLS LAST')
                ->first();

            $optOut = OptOut::query()
                ->where('identifier_type', $channel)
                ->where('value_hash', $hash)
                ->where('scope', $scope)
                ->where('business_id', $scope->requiresBusiness() ? Tenancy::idOrFail() : null)
                // NULLS LAST said out loud even though the column is NOT NULL —
                // the convention lint is right to insist, and a default that
                // becomes nullable later would otherwise put an undated row
                // ahead of every live one silently.
                ->orderByRaw('lift_generation DESC NULLS LAST')
                ->first();

            // ⚠️ THE CLASS IS READ FROM WHATEVER IS ACTUALLY SUPPRESSING THEM,
            // and the strictest one wins. A person can carry a STOP on the
            // tenant list and a complaint in the platform register at once —
            // lifting on the strength of the reversible one would leave the
            // complaint standing and report success, which is the worst of both:
            // an operator told the person is released, and a send path that
            // still refuses.
            //
            // The generation is gathered in the same pass. The two rows agree by
            // construction — `nextGeneration()` is one sequence per identifier
            // and channel, written to both stores in the same call — so the max
            // is a belt on the braces rather than a merge of two schemes.
            /** @var list<SuppressionReason> $classes */
            $classes = [];
            $generation = 0;

            if ($entry instanceof SuppressionListEntry) {
                $classes[] = $entry->reason_class;
                $generation = max($generation, $entry->lift_generation);
            }

            if ($optOut instanceof OptOut) {
                $classes[] = $optOut->reason_class;
                $generation = max($generation, $optOut->lift_generation);
            }

            if ($classes === []) {
                return false;
            }

            foreach ($classes as $class) {
                if ($class->isLiftable()) {
                    continue;
                }

                // ⚠️ `$entry ?? $optOut`, AND NOT `$entry` — see the note at
                // `consent.lifted` below. Reaching this line with no tenant-side
                // entry is an ordinary case, and 8046 leaves the entity
                // reference as the only route to what was refused.
                $this->audit->record('consent.lift_refused', $actor, $entry ?? $optOut, [
                    'channel' => $channel->value,
                    'reason_class' => $class->value,
                    'refusal' => $class->liftRefusal(),
                    'source' => $source->value,
                ]);

                return false;
            }

            // firstOrCreate, because a retried START is as ordinary as a retried
            // STOP and the unique key would otherwise raise on the second one.
            $lift = SuppressionLift::query()->firstOrCreate([
                'scope' => $scope,
                'business_id' => $scope->requiresBusiness() ? Tenancy::idOrFail() : null,
                'identifier_type' => $channel,
                'value_hash' => $hash,
                'generation' => $generation,
            ], [
                'source' => $source,
                'actor' => $actor,
                'note' => $note,
                'created_at' => now(),
            ]);

            if ($lift->wasRecentlyCreated) {
                // ⚠️ `29` §2 rule 42's record, and the one this whole method
                // exists to produce. The identifier does not travel, for the
                // reason `suppress()` gives at length (8046): the entity
                // reference is the route, and `audit_log` is kept for ever.
                //
                // ⛔ `$entry ?? $optOut`, AND THE FALLBACK IS NOT DEFENSIVE
                // PADDING — IT IS A REACHABLE ARM THAT WOULD OTHERWISE NAME
                // NOBODY AT ALL. `$entry` is this tenant's `suppression_list`
                // row and `$optOut` is the platform register's, and a lift needs
                // only one of them: a STOP that arrived on the shared Lane A
                // number through `suppressFromCarrier()` writes no tenant row at
                // all, and a STOP recorded for tenant A is invisible to tenant B
                // while the platform refusal is not. In those arms `$entry` is
                // null, and with the identifier gone from the metadata an entry
                // recorded against null would name neither a person nor a row —
                // an audit entry that cannot be investigated, which is the exact
                // harm 8044 raised the identifier to prevent.
                //
                // ⚠️ THE OPT-OUT NAMES A HASH RATHER THAN AN ADDRESS AND THAT IS
                // THE POINT. It confirms a candidate identifier
                // (`Identifier::hash()` is a keyed function of the normal form)
                // without holding one, which is the affordance `opt_outs` was
                // built to have.
                $this->audit->record('consent.lifted', $actor, $entry ?? $optOut, [
                    'channel' => $channel->value,
                    'source' => $source->value,
                    'scope' => $scope->value,
                    'generation' => $generation,
                    'note' => $note,
                ]);
            }

            return true;
        });
    }

    /**
     * The consent badge for a page of contacts, in a bounded number of queries
     * (`34` §1.1's badge column, `34` §1.2's header badge).
     *
     * ⚠️ **THIS EXISTS SO NO SCREEN EVER RUNS `proofFor()` PER ROW.** The list
     * shows twenty-five contacts, and a per-row read of two chokepointed tables
     * is the N+1 that would make widening the chokepoint look reasonable one
     * diff later (624's shape). Two queries whatever the page size: the consent
     * records, and the standing suppressions through `standingPredicate()` —
     * the same predicate the send gate and the Never-contact control use, so
     * the badge cannot drift from either.
     *
     * ⚠️ **STANDING, NOT THE TRAIL** — see `ConsentBadge`'s docblock: the trail
     * has no entry for a lift, so a trail-derived badge would keep a cleared
     * Never-contact on the screen forever, beside the control saying the
     * opposite.
     *
     * @param  Collection<int, Customer>  $customers
     * @return array<int, ConsentBadge> keyed by customer id
     */
    public function badgesFor(Collection $customers): array
    {
        if ($customers->isEmpty()) {
            return [];
        }

        $grants = ConsentRecord::query()
            ->whereIn('customer_id', $customers->map(fn (Customer $customer): int => (int) $customer->getKey())->all())
            ->get()
            ->groupBy('customer_id');

        /** @var list<array{int, OutreachChannel, string}> $pairs */
        $pairs = [];

        foreach ($customers as $customer) {
            foreach (OutreachChannel::cases() as $channel) {
                $identifier = $this->identifierFor($customer, $channel);

                if ($identifier !== null) {
                    $pairs[] = [(int) $customer->getKey(), $channel, $identifier];
                }
            }
        }

        $standing = $pairs === []
            ? collect()
            : SuppressionListEntry::query()
                ->where(function (Builder $query) use ($pairs): void {
                    foreach ($pairs as [, $channel, $identifier]) {
                        $query->orWhere(
                            fn (Builder $one): Builder => $this->standingPredicate($one, $identifier, $channel),
                        );
                    }
                })
                ->get();

        $badges = [];

        foreach ($customers as $customer) {
            $id = (int) $customer->getKey();

            $stopped = [];
            $classes = [];

            foreach ($pairs as [$pairId, $channel, $identifier]) {
                if ($pairId !== $id) {
                    continue;
                }

                $entry = $standing->first(
                    fn (SuppressionListEntry $candidate): bool => $candidate->channel === $channel
                        && $candidate->identifier === $identifier,
                );

                if ($entry instanceof SuppressionListEntry) {
                    $stopped[] = $channel;
                    $classes[] = $entry->reason_class;
                }
            }

            // ⚠️ **IN THE ENUM'S OWN ORDER, NEVER THE QUERY'S** (8085). The
            // grants query has no `ORDER BY` — it does not need one, because
            // this is a set — but `ConsentBadge::label()` renders the list as
            // prose, so whichever consent record Postgres happened to return
            // first decided whether the screen said *"text and email"* or
            // *"email and text"*. That made `CustomerListHeaderTest` flake, and
            // with no CI the pre-push hook is the whole gate: a randomly red
            // suite is one somebody re-runs until it is green, which is the
            // habit that lets a real failure through. Deriving the order from
            // `OutreachChannel::cases()` makes it a fact about the enum rather
            // than about the plan, and an `ORDER BY` would not have: two records
            // on one channel still tie.
            $held = $grants->get($id, collect())
                ->map(fn (ConsentRecord $record): OutreachChannel => $record->channel)
                ->all();

            /** @var list<OutreachChannel> $agreed */
            $agreed = array_values(array_filter(
                OutreachChannel::cases(),
                static fn (OutreachChannel $channel): bool => in_array($channel, $held, true),
            ));

            $badges[$id] = new ConsentBadge($agreed, $stopped, $classes);
        }

        return $badges;
    }

    /**
     * The complete consent trail for a contact, newest first (COMP-01 criterion
     * three, and `29` §19.4's build-failing "returns a complete trail").
     *
     * BOTH TABLES, NOT ONE. This used to return consent records alone, and for
     * anybody who had sent STOP that made it a document in which every row was
     * true and the whole was false: two grants of consent, no withdrawal, and no
     * hint that the controlling fact was missing. Decision 289 puts withdrawal
     * in suppression_list precisely because consent_records is append-only, so
     * an export reading only the grants can never show a revocation.
     *
     * There is one method rather than a proof-only one alongside a trail, so
     * there is no way to ask the misleading question by accident.
     *
     * A withdrawal is keyed on an identifier, not a customer, so the entries
     * matched are the ones for this customer's current phone and email. If the
     * contact details change, an older suppression against the previous number
     * stops appearing here — which is honest, since it is also no longer what
     * permit() would check.
     *
     * Ordering is done in PHP because the two tables cannot be ordered by one
     * SQL clause and their ids are not comparable to each other. Null timestamps
     * sort last, which is the same rule as the SQL above and for the same
     * reason: an undated row presented as the newest event would misstate the
     * one thing a reader takes from the first line.
     *
     * @return Collection<int, ConsentTrailEntry>
     */
    public function proofFor(Customer $customer): Collection
    {
        $consents = ConsentRecord::query()
            ->where('customer_id', $customer->id)
            ->orderByRaw('created_at DESC NULLS LAST')
            ->latest('id')
            ->get()
            ->map(fn (ConsentRecord $record): ConsentTrailEntry => ConsentTrailEntry::forConsent($record));

        $identifiers = array_values(array_filter([
            $this->identifierFor($customer, OutreachChannel::Sms),
            $this->identifierFor($customer, OutreachChannel::Email),
        ]));

        $withdrawals = $identifiers === []
            ? collect()
            : SuppressionListEntry::query()
                ->whereIn('identifier', $identifiers)
                ->orderByRaw('created_at DESC NULLS LAST')
                ->latest('id')
                ->get()
                ->map(fn (SuppressionListEntry $entry): ConsentTrailEntry => ConsentTrailEntry::forWithdrawal($entry));

        // A withdrawal wins a tie, and the tie is not hypothetical: created_at is
        // stored to the second, so a STOP arriving moments after a grant carries
        // the same timestamp. Sorting on time alone would then leave "still
        // consenting" at the top of the document, which is the one direction the
        // reader must not be misled in. When two events cannot be separated in
        // time, the safe one to present as current is the refusal.
        $at = fn (ConsentTrailEntry $entry): int => $entry->occurredAt?->getTimestamp() ?? PHP_INT_MIN;
        $withdrawalFirst = fn (ConsentTrailEntry $entry): int => $entry->type === ConsentEventType::Withdrawal ? 1 : 0;

        /** @var Collection<int, ConsentTrailEntry> $trail */
        $trail = $consents
            ->concat($withdrawals)
            ->sort(fn (ConsentTrailEntry $a, ConsentTrailEntry $b): int => [$at($b), $withdrawalFirst($b)] <=> [$at($a), $withdrawalFirst($a)])
            ->values();

        return $trail;
    }
}
