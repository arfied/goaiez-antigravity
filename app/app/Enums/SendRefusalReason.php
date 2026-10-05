<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Consent\ConsentService;

/**
 * Why `ConsentService` refused to grant a permit.
 *
 * ⚠️ THIS EXISTS BECAUSE A FAIL-CLOSED GATE WITH NO STATED REASON GETS "FIXED".
 * `permit()` returns `?SendPermit`, and null is deliberately the safe default —
 * but null carries no explanation, and the refusals this slice adds are ones
 * nobody has met before. An author in row 4 whose marketing campaign returns
 * null for every contact has three options: read this codebase until they find
 * the registry gate, ask, or pass `OutreachPurpose::Transactional` and watch it
 * start working. The third is a silent compliance regression and it is the
 * easiest of the three, which is exactly why the reason has to be reachable
 * without reading anything.
 *
 * ⚠️ NOT A SECOND CODE PATH. `ConsentService::decide()` produces both the permit
 * and the reason from one traversal, and `permit()` unwraps it — the same
 * construction as `proofFor()` being one method rather than a trail alongside a
 * proof, so there is no way for the answer and the explanation to disagree.
 *
 * ⚠️ SAFE TO SHOW AN OPERATOR, NOT A CUSTOMER. Each case names a rule, never a
 * person: no identifier, no hash, no list entry. `audit_log` and the activity
 * feed already keep contact details out for the same reason, and a refusal
 * reason will end up in both.
 *
 * ⛔ **AND SINCE 2570 EACH CASE ALSO ANSWERS WHETHER IT WILL STILL BE TRUE
 * TOMORROW** — {@see self::isTemporary()}. A sender that treats every refusal as
 * final destroys the recipient over a condition measured in hours, which is what
 * `RunCampaignJob` did to an entire audience the moment a campaign started after
 * 21:00.
 */
enum SendRefusalReason: string
{
    /**
     * The owner archived this contact (`44` §8, decision 1327). Archive is the
     * owner's tidying — hidden from lists, excluded from sends, restorable —
     * and it is deliberately NOT a `SuppressionReason`: folding it in would
     * file the owner's housekeeping to `audit_log` as `consent.withdrawn`, a
     * statement we authored in the table whose job is to be true (1225's
     * reasoning). Refuses every purpose, transactional included: "excluded
     * from sends" in `44` §8 is unqualified.
     */
    case Archived = 'archived';

    /**
     * The owner deleted this contact (`34` §1.2, `44` §8's D-205, decision
     * 1540). A tombstone: the row survives, the person is gone from the
     * owner's book, and the undo expires after seven days.
     *
     * ⚠️ **A FOURTH CASE, AND IT MUST NEVER BE FOLDED INTO `Archived`.** The two
     * refuse the same sends and mean different things — archive is "put them
     * out of the way", delete is "they are not my customer" — and the operator
     * reading this reason is deciding whether the contact can be brought back
     * from a screen or is past its window. 1225 refused the same collapse on
     * the suppression side and 552 on the consent side; the answer is the same
     * here, because a label that is nearly true is the kind a reader trusts.
     */
    case Deleted = 'deleted';

    /**
     * This contact was folded into another one (`34` §1.2, `CustomerMerges`).
     * It is not a person the owner hid and not somebody who refused — it is a
     * former spelling of a customer who lives at another row, and the message
     * belongs to that row.
     *
     * ⚠️ **ASKED BEFORE THE IDENTIFIER, WHICH IS WHY IT IS A CASE AT ALL.** A
     * merge empties the merged-away row's email and phone, so a gate that asked
     * about the identifier first would refuse every one of these as
     * `NoIdentifier` and look perfectly correct — a right answer for a wrong
     * reason, which sends an operator looking for contact details that were
     * never missing. The same trap as `UnparseableIdentifier` being named apart
     * from `OptedOut` two cases below.
     */
    case MergedAway = 'merged_away';

    /**
     * The customer has no phone number or address on this channel at all.
     */
    case NoIdentifier = 'no_identifier';

    /**
     * The identifier could not be normalised, so it cannot be compared against
     * a stored hash either. `Identifier::hash()` returning null fails closed —
     * an unparseable number that looked sendable would be permanently
     * un-suppressible.
     */
    case UnparseableIdentifier = 'unparseable_identifier';

    /**
     * The contact this send resolved to is **not the person who contacted us**.
     *
     * ⛔ **THE ONLY CASE HERE THAT IS ABOUT A THIRD PARTY RATHER THAN ABOUT THE
     * RECIPIENT** (3179). A reply to an inbound event — the missed-call
     * text-back, and anything `SL-5` later builds on the same shape — resolves a
     * `Customer` from an id the webhook supplied and then texts the identifier
     * the permit names. Those are two different lookups, and when they disagree
     * the message goes to **somebody who did not call us**: a contact whose
     * number was edited after the call, a household number shared between two
     * rows, or a webhook lane that resolved the wrong contact.
     *
     * ⚠️ **`InboundCall`'s DOCBLOCK ASSERTS THIS CANNOT HAPPEN AND NOTHING
     * ENFORCED IT** — *"the number the text-back goes to is the number consent
     * and suppression are asked about"*, which is 314–316's shape: a protection
     * claim written before the mechanism. This case is the mechanism.
     *
     * ⚠️ **NOT `NoIdentifier` AND NOT `UnparseableIdentifier`.** The contact has
     * a perfectly good number; it is simply not the one that rang. `Deleted`'s
     * own docblock argues the general point — *"a label that is nearly true is
     * the kind a reader trusts"* — and an operator sent to look for a missing
     * mobile would find one and stop looking.
     */
    case CallerMismatch = 'caller_mismatch';

    /**
     * Somebody said STOP. Either the platform-scoped `opt_outs` register or the
     * tenant's own `suppression_list`.
     */
    case OptedOut = 'opted_out';

    /**
     * On a Do Not Call registry, federal or state.
     */
    case DoNotCall = 'do_not_call';

    /**
     * On a known-litigator list. Blocks transactional messages too.
     */
    case Litigator = 'litigator';

    /**
     * The number was reassigned after the consent record was captured, so the
     * consent belongs to the previous subscriber.
     */
    case NumberReassigned = 'number_reassigned';

    /**
     * No consent record exists for this customer on this channel. `29` §2 rule
     * 6: "a contact with no consent record can never be texted at all".
     */
    case NoConsentRecord = 'no_consent_record';

    /**
     * The only record is `ConsentType::ImpliedByCall`: the person rang the business, which lets it reply about that call and
     * nothing more (owner ruling D-1, 2026-10-05). Durable — only a real consent capture lifts it.
     */
    case RepliesOnly = 'replies_only';

    /**
     * ⚠️ THE ONE THAT WILL BE MET FIRST AND READ AS A BUG. No scrubbing register
     * has ever been loaded, so a marketing send cannot be proved clean. Every
     * marketing send is refused until `compliance_suppressions` has been
     * populated at least once — `SuppressionRegistry::isLoaded()`.
     *
     * Transactional sends are unaffected, which is every message this product
     * sends today.
     */
    case RegistryNotLoaded = 'registry_not_loaded';

    /**
     * ⛔ **THE SUPPRESSION REGISTERS CANNOT ANSWER, WHICH IS NOT THE SAME FACT
     * AS NOBODY HAVING OPTED OUT** (8080). Every stored suppression is a keyed
     * hash — `Identifier::hash()`, keyed on `APP_KEY` — and after a rotation a
     * freshly computed hash matches none of them: `opt_outs` refuses nobody, the
     * DNC and litigator registers refuse nobody, and no lift can be paired with
     * the refusal it clears.
     *
     * ⚠️ **NOT `OptedOut`, AND THE DISTINCTION IS THE WHOLE DELIVERABLE.**
     * `Deleted`'s own docblock argues the general point — *"a label that is
     * nearly true is the kind a reader trusts"* — and here the nearly-true label
     * would send an operator hunting for a STOP that may never have happened,
     * for every contact on the platform at once. `UnparseableIdentifier` was
     * named apart from `OptedOut` for exactly this reason two cases up; this is
     * the same argument about the register rather than about the number.
     *
     * ⚠️ **NOT `RegistryNotLoaded` EITHER.** That one says *import a register*,
     * and importing one here would write rows under the new key while every row
     * written under the old one stayed inert — an operator doing the thing they
     * were told to do and making the situation worse.
     *
     * ⚠️ **IT REFUSES TRANSACTIONAL SENDS TOO**, which no other register-shaped
     * refusal on this enum does. A transactional message to somebody who sent
     * STOP is precisely the violation, and `24` §3.3 makes transactional every
     * message this product sends.
     *
     * The way out is `php artisan consent:hash-epoch`, which prints both.
     */
    case SuppressionUnreadable = 'suppression_unreadable';

    /**
     * ⚠️ THE SECOND ONE THAT WILL BE MET, AND THE HONEST NAME FOR A MISSING
     * COLUMN. `29` §2 rule 11 requires state mini-TCPA rules to be applied, and
     * nothing in this schema records which state a customer is in — `customers`
     * has no state column and `locations.address` is one free-text string.
     * Several states are stricter than federal and the list moves, so marketing
     * to an unknown jurisdiction is refused rather than treated as federal-only.
     */
    case StateUnknown = 'state_unknown';

    /**
     * The recipient's state prohibits messaging at this hour.
     */
    case QuietHours = 'quiet_hours';

    /**
     * The recipient's state requires prior express *written* consent for
     * marketing and the record on file is plain express consent.
     */
    case ConsentTooWeakForState = 'consent_too_weak_for_state';

    /**
     * This tenant's sending is paused — T137 `SL-8`'s per-tenant kill switch.
     *
     * ⚠️ **NOTHING ABOUT THE RECIPIENT.** Every other case on this enum is a
     * fact about the person being messaged; these last three are facts about
     * *us*, and an operator reading a refusal needs to know which kind it is
     * before they start looking at a contact record that is perfectly fine.
     *
     * 2102: the pause behind this is automatic on a complaint-rate threshold,
     * because the failure mode is a campaign running overnight while the queue
     * nobody is watching fills.
     */
    case TenantPaused = 'tenant_paused';

    /**
     * All sending on the platform is halted.
     *
     * `SL-8`'s global halt. Checked before the per-tenant pause so that an
     * operator stopping everything does not have to reason about ten thousand
     * tenant rows, and so the refusal an operator sees names the switch they
     * actually threw.
     */
    case GlobalHalt = 'global_halt';

    /**
     * The tenant's balance cannot pay for this send.
     *
     * ⚠️ **A REFUSAL, NEVER A HARD FAILURE, AND NEVER A SURPRISE CHARGE.**
     * `29` §2 rule 43: per-tenant cost caps degrade gracefully. The send does
     * not happen, the caller records why, and nothing is billed — auto-top-up
     * is opt-in with a tenant-set ceiling (2064) and is a CONFIRM action
     * because it spends money.
     */
    case InsufficientCredit = 'insufficient_credit';

    /**
     * The channel itself could not carry this message.
     *
     * ⚠️ **THE ONE REFUSAL A `SendDriver` IS ALLOWED TO MAKE, AND IT IS ABOUT
     * THE TRANSPORT RATHER THAN ABOUT ANYBODY** — `SendDriver::deliver()`'s
     * rule: *"a driver may only return refused for a fact about this channel's
     * own transport."* Every compliance refusal above happened before a driver
     * was reached.
     *
     * ⚠️ **IT COVERS TWO STATES THAT `PlatformTexter` DELIBERATELY DOES NOT
     * DISTINGUISH** (decision 2550): the `sms.enabled` kill switch being off,
     * and an inventory in which every number is quarantined, retired or still
     * registering. That class's own comment insists *"the two nulls are not the
     * same null"* — and the difference it protects is a *behavioural* one
     * (whether the driver may fall back to the configured sender), which it
     * settles internally. What comes back out is a single null, so a second
     * case here would be this enum claiming a distinction the value it is
     * derived from cannot make. An operator who needs to tell them apart reads
     * the switch, which is one query.
     */
    case ChannelUnavailable = 'channel_unavailable';

    /**
     * The message could not be composed for this one contact.
     *
     * ⚠️ **THE ONLY CASE HERE THAT IS NOT A COMPLIANCE OR TRANSPORT FACT**, and
     * it exists because 2687 needs the campaign runner's `MessageCannotBeComposed`
     * branch to be able to *end*. That branch called `markSkipped()` under a
     * comment reading *"Refused individually"* — a contradiction 2589 caught —
     * so a contact whose name will never fit the 159-unit budget was retried
     * every fifteen minutes for ever, exactly like the `StateUnknown` case
     * beside it. Giving up needs a reason, the CHECK
     * `campaign_recipients_reason_belongs_to_a_refusal` insists on one, and none
     * of the eighteen cases above was true.
     *
     * ⚠️ **CLASSIFIED PERMANENT, THOUGH IT IS THE ARGUABLE ONE.** A contact's
     * name can be edited and the message would then fit, so this is closer to
     * `StateUnknown` than to `OptedOut`. It is `false` because by the time it is
     * *written* the fortnight has already passed with nobody editing anything —
     * the runner defers it exactly like a temporary refusal and only reaches for
     * this case at the ceiling. **A `true` here would mean the ceiling could
     * never be reached**, which is the defect this case was added to close.
     */
    case MessageTooLong = 'message_too_long';

    /**
     * Whether this refusal is a *state of the world right now* rather than a
     * fact about whether this person may be messaged at all.
     *
     * ⛔ **THE DEFECT THIS EXISTS TO CLOSE WAS DESTRUCTIVE, NOT MERELY INERT**
     * (2570). `RunCampaignJob::sendTo()` answered every refusal by marking the
     * recipient `Refused`, which is terminal — the runner's own batch query
     * never looks at a refused row again. So a reactivation campaign started at
     * 21:30 consumed its **entire audience on the first pass**, with zero
     * messages sent, and left no list to recover: `QuietHours` is false again by
     * breakfast, and the rows that were dropped for it are not. `StateUnknown`
     * did the same to any imported list with no state column.
     *
     * ⚠️ **THE RUNNER ALREADY KNEW THE DISTINCTION AND APPLIED IT ONE BRANCH
     * DOWN.** The `S1` arbiter collision is marked `Skipped` under the comment
     * *"NOT `Refused`, BECAUSE IT IS TRUE AGAIN TOMORROW"*, and 2454 marks all
     * four of the runner's stop conditions the same way for the same reason.
     * Quiet hours is the textbook case of that sentence and took the other path.
     *
     * ⚠️ **TEMPORARY DOES NOT MEAN "SEND LATER WITHOUT ASKING", AND THAT IS WHY
     * IT IS SAFE.** It means the recipient row stays outstanding, so a later
     * pass puts them back through {@see ConsentService::decide()}
     * from the top. Nothing is cached, no permit is carried forward, and a
     * contact who is still refused is refused again. The only thing this
     * predicate decides is whether the **list survives** — never whether a
     * message goes out.
     *
     * ⛔ **THE ASYMMETRY IS DELIBERATE: A PERMANENT REFUSAL WRONGLY MARKED
     * TEMPORARY IS THE WORSE ERROR**, so anything that is a fact about the
     * *person* — they said STOP, they are on a register, there is no consent
     * record, the number belongs to somebody else now — is permanent, and only
     * facts about the *clock*, the *platform* or *our own missing data* are
     * temporary. A `match` with no `default`, so a new case is a compile-time
     * conversation rather than one that quietly inherits the permissive answer.
     */
    public function isTemporary(): bool
    {
        return match ($this) {
            // The clock. False again in a few hours, every day, by definition.
            // This is the case the whole predicate exists for.
            self::QuietHours,

            // ⚠️ **OURS, NOT THE RECIPIENT'S** — no scrubbing register has ever
            // been imported, so no marketing send can be proved clean. One
            // `suppression:load` run clears it for every tenant at once, and
            // burning every campaign audience on the platform because *we* have
            // not imported a list would be this defect with our own paperwork
            // as the cause.
            self::RegistryNotLoaded,

            // ⚠️ **OURS, AND THE MOST RECOVERABLE REFUSAL ON THIS ENUM** —
            // putting the previous `APP_KEY` back in `.env` clears it for every
            // tenant at once and loses nothing. Marking it permanent would burn
            // every campaign audience on the platform over our own key
            // management, which is 2570's defect with the operator's `.env` as
            // the cause. ⛔ **Temporary here is not "send later without
            // asking"** — the docblock above is explicit that the only thing
            // this predicate decides is whether the list survives, and a
            // recipient put back through `decide()` while the key is still
            // wrong is refused again.
            self::SuppressionUnreadable,

            // ⚠️ **A BLANK COLUMN, NOT A JURISDICTION THAT REFUSES.** Nobody has
            // answered for this contact's state yet, and the column has writers
            // (`CustomerEditor::setRegion()`, and a `state` column on an
            // imported list — 1594). The send stays refused until somebody
            // answers; the contact does not stop being a contact meanwhile.
            // ⚠️ This is the genuinely ambiguous one, and it is called
            // temporary because temporary is *also* the conservative answer
            // here: the gate is re-asked in full on every pass, so nothing is
            // sent either way, and the only difference is whether the list is
            // still there when somebody fills the column in.
            self::StateUnknown,

            // The last three are facts about *us*, which 2454 already ruled on
            // for the runner's own stop conditions: a person releases them, and
            // marking the audience refused would mean that releasing the pause
            // recovered the sending and lost the list.
            self::TenantPaused,
            self::GlobalHalt,

            // `29` §2 rule 43 — cost caps degrade gracefully and never hard-fail.
            // A balance that ran out mid-campaign is a billing event; making it
            // destroy the remaining audience would turn a top-up into a rebuild.
            self::InsufficientCredit,

            // ⚠️ **ADDED AT THE MERGE OF `l3-campaign-runner-fixes` AND
            // `l2-message-sender`, WHICH IS THE POINT OF HAVING NO `default`.**
            // The two lanes were built in parallel: one added this predicate,
            // the other added the case, and neither could see the other. A
            // `match` without a `default` turned that into a compile-time
            // conversation instead of a silent inheritance of the permissive
            // answer — exactly what the docblock above promises it would.
            //
            // It is temporary, and it is the same family as `TenantPaused` and
            // `GlobalHalt` above: a fact about **our** transport, never about
            // the recipient. It means the `sms.enabled` switch is off or every
            // number in the inventory is quarantined, retired or still
            // registering (2550). Both are states a person or a registration
            // clears, and burning a campaign's whole audience because our own
            // kill switch was off would be 2570's defect with our own
            // paperwork as the cause — which is the argument
            // `RegistryNotLoaded` already makes one arm up.
            self::ChannelUnavailable => true,

            // ⛔ **STOP. The one that must never be retried**, and the reason
            // this method refuses to have a `default`.
            self::OptedOut,

            // On a register. Not ours to move, and not moving on its own.
            self::DoNotCall,
            self::Litigator,

            // The consent on file belongs to the previous subscriber. A later
            // pass would find exactly the same thing.
            self::NumberReassigned,

            // `29` §2 rule 6: a contact with no consent record can never be
            // texted at all. A campaign pass is not where that changes.
            self::NoConsentRecord,

            // ⚠️ **NEEDS A DIFFERENT CONSENT RECORD, NOT A DIFFERENT HOUR** —
            // 1620 names this one explicitly as the durable refusal that must
            // not be hidden behind `QuietHours`, because it *"will still refuse
            // at noon"*. Classifying it temporary would re-attempt it every
            // pass, forever, against a rule that only a new consent capture can
            // satisfy.
            self::ConsentTooWeakForState,

            // ⚠️ **NEEDS A REAL CONSENT, NOT A DIFFERENT HOUR** — a caller who only rang can be replied to about that call; a
            // campaign retrying them would never succeed until they actually agree to more.
            self::RepliesOnly,

            // ⚠️ **THE OWNER'S OWN INSTRUCTIONS, AND THE CAMPAIGN HAS NO
            // STANDING TO WAIT THEM OUT.** All three are reversible by a
            // person — un-archive, restore inside the seven days, and a merge
            // has a target row — but reversing one is a decision about the
            // contact, not about this campaign, and holding the recipient
            // outstanding meanwhile would keep the campaign from ever closing
            // over a row the owner has deliberately put away.
            self::Archived,
            self::Deleted,
            self::MergedAway,

            // No phone at all, or one that will not normalise. Neither fixes
            // itself, and a campaign audience is materialised at enrolment
            // rather than re-resolved, so nothing here will ever supply one.
            self::NoIdentifier,
            self::UnparseableIdentifier,

            // ⚠️ **PERMANENT FOR THE OCCASION IT REFUSED, WHICH IS THE ONLY
            // THING THIS PREDICATE DECIDES.** The number that rang is a fact
            // frozen at the moment of the call; retrying will compare the same
            // two values and disagree again. The docblock's asymmetry applies
            // straightforwardly — a permanent refusal wrongly marked temporary
            // is the worse error, and re-attempting this one would keep
            // re-deciding whether to text a third party.
            self::CallerMismatch,

            // ⚠️ **WRITTEN ONLY AT THE CEILING, WHICH IS WHY IT IS PERMANENT
            // DESPITE BEING FIXABLE** (2687). See the case's own docblock: the
            // runner defers a composition failure exactly like a temporary
            // refusal, and reaches for this case only after the fortnight has
            // passed with nobody editing the name. `true` here would mean the
            // ceiling could never be reached.
            self::MessageTooLong => false,
        };
    }

    /**
     * What to tell the owner when a campaign gave up on somebody for this
     * reason.
     *
     * ⛔ **DECISION 2687'S ACTUAL DELIVERABLE, WHICH IS THIS SENTENCE AND NOT
     * THE CEILING.** *"'5,000 contacts could not be sent to: no state on file'
     * is a thing somebody fixes; a queue quietly retrying forever is not."* The
     * ceiling stops the spin; without a sentence naming a cause the owner can
     * act on, all it buys is a campaign that fails silently in a fortnight
     * instead of never.
     *
     * ⚠️ **A CAUSE, IN THE OWNER'S WORDS — NOT THE CASE VALUE.** `22`: outcome
     * language only, every string names what the person controls and never how
     * the system is built. `state_unknown` printed on a screen is the column
     * name; *"no state on file"* is the thing they can go and fix. This is
     * 2652's ruling applied one enum over, and for the same reason: a template
     * choosing its own wording is one that drifts from the next template.
     *
     * ⚠️ **STILL NEVER A CONTACT DETAIL.** Every case names a rule or a state of
     * the world, exactly as this enum's own docblock promises — so these are
     * safe on a screen, in a log line and in an activity item where a phone
     * number would not be.
     *
     * A `match` with no `default`, so **a new case is a conversation** rather
     * than an `UnhandledMatchError` on an owner's screen.
     *
     * ⛔ **THIS SENTENCE COUNTED THE CASES — "a nineteenth case" — AND WAS
     * ALREADY WRONG BY ONE BEFORE 8080 ADDED THE TWENTY-FIRST.** The number was
     * decoration: the property is the absent `default`, and a count in a
     * docblock beside a list the docblock does not derive is 2505's shape in
     * miniature. `MessageTooLong`'s *"none of the eighteen cases above was
     * true"* carries the same arithmetic and is **deliberately left**, because
     * it is 2687's account of the moment that case was added rather than a claim
     * about today (4368's rule).
     *
     * ⛔ **THREE OF THESE SENTENCES NAMED THE SMS IDENTIFIER BY NAME, AND THAT
     * WAS TRUE OF EVERYWHERE THIS WAS READ UNTIL THE EMAIL CHANNEL COULD
     * PRODUCE THEM TOO — 10240, PHASE 2.** `NoIdentifier`, `UnparseableIdentifier`
     * and `ChannelUnavailable` used to read *"we have no mobile number for
     * them"*, *"their mobile number could not be read"* and *"text messaging
     * was unavailable"* unconditionally, which was correct on both call sites
     * this method had — `RunCampaignJob` and `Livewire\Account\Inbox` are both
     * SMS-only — right up until wave 34 made `ReviewInviteSender::sendEmail()`
     * answer all three of these for real: a customer with no email address, an
     * address that will not normalise, or `PlatformMailer::customerMailRefusal()`
     * refusing the transport. **A refused email invite could then carry a
     * sentence about a mobile number nobody was ever going to use.** The
     * parameter is required rather than defaulted, on this enum's own
     * `match`-with-no-`default` convention: a caller must say which channel it
     * is explaining, and the two existing callers now do.
     *
     * ⚠️ **`$channel->usesPhoneIdentifier()`, NOT A SECOND `match` ON THE
     * CHANNEL ITSELF.** `OutreachChannel::Whatsapp` shares SMS's phone
     * identifier and `ReviewsTest`'s *"WhatsApp is never offered on an
     * owner-facing surface"* lint allows exactly three files to spell that case
     * — this is not one of them. Asking the channel rather than naming its
     * cases is the same argument `usesPhoneIdentifier()`'s own docblock makes:
     * the fact belongs to the channel, and asking it leaves this method no
     * reason to write the word at all.
     */
    public function ownerSentence(OutreachChannel $channel): string
    {
        return match ($this) {
            self::QuietHours => 'it was outside the hours their state allows',
            self::RegistryNotLoaded => 'our do-not-call checks are not ready yet',
            self::SuppressionUnreadable => 'our record of who has asked us to stop could not be read',
            self::StateUnknown => 'no state on file',
            self::ConsentTooWeakForState => 'their state needs a stronger consent than we hold',
            self::TenantPaused => 'sending was stopped for your account',
            self::GlobalHalt => 'sending was stopped for everyone',
            self::InsufficientCredit => 'the message balance ran out',
            // ⚠️ **`Voice` NEEDS A THIRD ANSWER, AND `usesPhoneIdentifier()`
            // ALONE CANNOT GIVE ONE** (wave 39 lane B). That fact is boolean —
            // true for Sms, Whatsapp and Voice alike — so it was fine while
            // "phone-shaped" meant "text messaging" with no other member. A
            // direct `$channel === OutreachChannel::Voice` comparison is safe
            // to write here (unlike Whatsapp, nothing forbids naming Voice
            // outside the consent lane — `ReviewsTest`'s allowlist exists only
            // for the literal word "whatsapp"). `NoIdentifier` and
            // `UnparseableIdentifier` below are deliberately NOT given the same
            // treatment: both describe the *identifier*, and a call's
            // identifier genuinely is the same mobile number SMS uses, so their
            // existing phone/email split stays correct for Voice unchanged.
            self::ChannelUnavailable => match (true) {
                $channel === OutreachChannel::Voice => 'calling was unavailable',
                $channel->usesPhoneIdentifier() => 'text messaging was unavailable',
                default => 'email was unavailable',
            },
            self::OptedOut => 'they asked us to stop',
            self::DoNotCall => 'they are on the Do Not Call register',
            self::Litigator => 'they are on a suppression list',
            self::NumberReassigned => 'their number now belongs to somebody else',
            self::NoConsentRecord => 'we have no record of their permission',
            self::RepliesOnly => 'they only called you, which lets us reply about the call and nothing more',
            self::Archived => 'you archived them',
            self::Deleted => 'you deleted them',
            self::MergedAway => 'you merged them into another contact',
            self::NoIdentifier => $channel->usesPhoneIdentifier()
                ? 'we have no mobile number for them'
                : 'we have no email address for them',
            self::UnparseableIdentifier => $channel->usesPhoneIdentifier()
                ? 'their mobile number could not be read'
                : 'their email address could not be read',
            self::CallerMismatch => 'the number that contacted us is not the one on their record',
            self::MessageTooLong => 'the message would not fit for them',
        };
    }
}
