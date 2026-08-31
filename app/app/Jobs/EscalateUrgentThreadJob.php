<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MessageSender;
use App\Enums\AgentSkill;
use App\Enums\AutopilotActionType;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\OwnerNotificationKind;
use App\Enums\SendRefusalReason;
use App\Exceptions\TextNotDeliverable;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\UrgentMessageEscalated;
use App\Services\Agent\AgentComposer;
use App\Services\Agent\AgentSkills;
use App\Services\Agent\AgentThreadStates;
use App\Services\Agent\AgentTurns;
use App\Services\Ai\AiSpend;
use App\Services\Assistant\UrgentTerms;
use App\Services\Consent\ConsentService;
use App\Services\Consent\OwnerConsentService;
use App\Services\Consent\SendPermit;
use App\Services\Mail\PlatformMailer;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendKey;
use App\Services\Sms\PlatformTexter;
use App\Services\Sms\SentText;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Skill 9's two messages — page the owner, then tell the customer — T176 §2.2,
 * decisions 5323, 5334(b).
 *
 * §2.2 row 9: *"tenant-defined urgent terms (lockout, leak, flood, no-heat…) →
 * immediate owner SMS+email ping; give the tenant's emergency line if set;
 * life-safety → advise emergency services, no advice beyond that."*
 *
 * ## ⛔ WHAT WAS MISSING WAS EVERYTHING AFTER THE PROMPT
 *
 * {@see UrgentTerms::matches()} had **zero callers in `app/`** — 5323, verified
 * by grep and by `AgentThreadStates::escalate()`'s own docblock admitting it.
 * A tenant's urgent words lit skill 9 in {@see AgentSkills},
 * reached the model as a line of text, and **nothing turned one into an
 * escalation, an owner notification, or a handing-out of
 * `assistant_briefs.emergency_line`**. 272's shape on a safety feature, and its
 * tell in full: every test of the terms store passed against a matcher nothing
 * called.
 *
 * ⚠️ **THE MITIGATION IT REPLACES IS A PROMPT INSTRUCTION AND STAYS ONE.**
 * {@see AgentComposer}'s standing lines carry *"if the message describes
 * something life-threatening … tell them to call the emergency services
 * immediately"* unconditionally, and nothing deterministic enforces its
 * ordering or even its presence in the output. This is the deterministic path
 * **beside** it, not a replacement for it: a life-threatening message that
 * contains none of the tenant's words still reaches the model with that
 * instruction and nothing else.
 *
 * ## ⛔ THE MODEL IS NOT ASKED, AND THAT IS THE POINT OF THE WHOLE PATH
 *
 * {@see AgentTurns} escalates the thread **synchronously**, before this job is
 * dispatched, and this job composes a **static** template. So:
 *
 *  - {@see self::canExecute()} is not overridden, so unlike
 *    {@see AnswerAgentTurnJob} this does **not** gate on {@see AiSpend}. An
 *    exhausted AI balance is a thing that comes back; a customer with a gas leak
 *    is not, and *"we could not afford a completion"* is the worst possible
 *    reason not to page an owner;
 *  - there is no fence, no guardrail pass and no rail-5 lint, because none of
 *    them has a subject — a model wrote none of these words;
 *  - **no AI ledger is debited** and none should be (3297): this path makes no
 *    provider call. ⚠️ **IT MAKES TWO SENDS AND ONLY ONE OF THEM DEBITS —
 *    THIS SAID "THE ONE SEND" UNTIL WAVE 40 (10971).** `acknowledge()` texts
 *    the CUSTOMER and debits `SendCredits` inside `PlatformMessageSender` like
 *    every other customer-facing send; `textOwner()` texts the ACCOUNT HOLDER
 *    and deliberately debits nothing (10546). The sentence was true when it
 *    was written and wave 38's own owner text falsified it four paragraphs
 *    from where it was added.
 *
 * ## ⛔ THE THREAD IS ALREADY SILENCED WHEN THIS RUNS, SO IT MAY NOT ASK
 *
 * {@see SendAgentNudgeJob} refuses to send when `agentMaySpeak()` is false, and
 * copying that check here would refuse **every** run: `AgentTurns` moved the
 * thread to `Escalated` on purpose, one line before dispatching this. The
 * question this job would be asking is *"may the assistant take a turn"*, and
 * this is not a turn — no turn is counted, rail 3's cap does not move, and the
 * message says the assistant has **stopped**. The gate that matters was taken
 * synchronously by `AgentTurns::escalateAsUrgent()` against the state *before*
 * the escalation, where a latched, capped or closed thread is refused.
 *
 * ## The owner first, and the claim held from that moment
 *
 * ⛔ **THE PAGE GOES BEFORE THE ACKNOWLEDGEMENT AND NEVER AFTER IT.** Everything
 * downstream of the permit can refuse — no consent record, a suppression, the
 * platform halt, an exhausted SMS balance — and an owner who is only told once
 * the customer was successfully texted is an owner who is told least often
 * exactly when the customer heard nothing.
 *
 * ⚠️ **SO {@see self::claimIsSpent()} IS ANSWERED FROM THE PAGE RATHER THAN FROM
 * THE SEND**, which is the opposite of {@see SendMissedCallTextBackJob}'s
 * answer and is a deliberate trade. A retry would re-page the owner, and a
 * duplicated *URGENT* mail is the alert-fatigue failure `OperatorAlerts`
 * de-duplicates against — on the one alert that has to stay audible. A lost
 * acknowledgement is recoverable and the owner has already been paged; a
 * second page teaches somebody to ignore the first.
 *
 * ## ⛔ *"The owner has already been paged"* was answered by a METHOD HAVING RUN — 11140
 *
 * ⛔ **THE SENTENCE ABOVE IS KEPT AND ITS SUBJECT HAS CHANGED, AND THE
 * DIFFERENCE IS THE WHOLE OF 11140.** *"The page"* meant {@see self::page()}
 * **having executed**, and that method sent through
 * {@see PlatformMailer::send()} — a `DeliverPlatformMail::dispatch()` inside a
 * `catch (Throwable)` that logs and returns `void` (9500). So a message the
 * transport never accepted returned normally, `$paged` became `true` one line
 * later, and three things followed that are all false together: the run row
 * closed `Succeeded` carrying `'paged' => true`, {@see self::acknowledge()}
 * told a member of the public *"I have passed this straight to {business} as
 * urgent"*, and `claimIsSpent()` held the key **for ever** — the
 * `agent-urgent:<conversation>:<carrier message id>` claim is unique-indexed,
 * so no later dispatch about that inbound message can ever run again. **The
 * only recovery was a second text from the customer.**
 *
 * ⛔ **IT WAS DRIVEN RATHER THAN READ.** With the active mailer's daily sending
 * ceiling left unstated — the state of a fresh deployment, and the one guard
 * {@see PlatformMailer::canDeliver()} structurally cannot see (10852) —
 * `Notification::assertNothingSent()` and `'paged' => true` were both true in
 * the same run, and the customer's stored message said the owner had been
 * told. ⚠️ **The registry row is named by shape rather than spelled**:
 * `MailTest`'s *"the sending ceiling is read through MailQuota and nowhere
 * else"* refuses that literal anywhere under `app/`, docblocks included, and
 * it is what caught the first draft of this paragraph.
 *
 * ✅ **`$paged` NOW MEANS *THE OWNER WAS REACHED ON AT LEAST ONE CHANNEL*.**
 * The mail goes through {@see PlatformMailer::deliverNow()}, which **throws**,
 * so this job can tell the difference; the text is attempted whatever the mail
 * did; and the flag is the union. ⚠️ **THIS IS NOT
 * {@see SummariseClosedThreadJob}'s ANSWER TRANSPLANTED HERE AND MUST NOT BE
 * READ AS ONE.** That job releases its claim *until the notify has gone* and
 * would rather mail twice than not at all; this one still holds from the page,
 * for the reason above — what changed is that *"the page"* is now a fact about
 * a transport rather than about a program counter.
 *
 * ## ⛔ Reached on nothing is a failure; reached on one channel is a degradation
 *
 * ⛔ **{@see self::escalate()} THROWS ONLY WHEN THE OWNER WAS REACHED BY
 * NOTHING**, and returns normally when the mail failed and the text went. That
 * asymmetry is `29` §2 rule 43's surviving half — graceful degradation, never
 * hard-fail — and it is what keeps the honest account **on the run row**:
 * {@see AutopilotJob::handle()} writes `output` only on the success arm, so a
 * throw costs the operator every one of the counts and handles this job
 * assembles, in exchange for `error` and a retry ladder. **On the arm where
 * somebody was told, that is a bad trade; on the arm where nobody was, the
 * whole story is the error.**
 *
 * ⚠️ **AND ON THE THROWING ARM THE LADDER IS THE BELL.** Three attempts, then
 * {@see AutopilotJob::failed()} rings `AutomationAbandoned` **carrying
 * `business_id`** — which is strictly better than what this path had, because
 * `PlatformMailUndeliverable` is deduped *per mailer* and names no account
 * (9370–9379). ⚠️ **That bell is not lost from the platform either**: every
 * other queued mail path still rings it, and this one now says *which tenant's
 * urgent page went nowhere*.
 *
 * ⛔ **THERE IS DELIBERATELY NO {@see PlatformMailer::canDeliver()} GATE IN
 * FRONT OF THE SEND** — `App\Jobs\Voice\NotifyOwnerOfVoicemailJob`'s
 * argument, verbatim in its application here: the daily sweeps gate because a
 * `.env` fault is identical for every business they walk and tomorrow's run
 * asks again. **This job is event-driven and there is no tomorrow.** A gate
 * would turn an unconfigured mail system into a clean return — no bell, no
 * `failed_jobs` row, no retry — for a message a member of the public is
 * waiting on.
 */
final class EscalateUrgentThreadJob extends AutopilotJob
{
    private bool $paged = false;

    /**
     * Why the owner's mail did not go, held for {@see self::escalate()}.
     *
     * ⚠️ **A PROPERTY RATHER THAN A RETURN VALUE BECAUSE IT IS RETHROWN, NOT
     * REPORTED.** It never reaches `automation_runs.output` — the run row gets
     * the class name and nothing else (`mail_refusal`), on
     * `DeliverPlatformMail::failed()`'s *"ours or nothing"* rule.
     *
     * ⚠️ **AND IT IS NOT READABLE FROM {@see AutopilotJob::failed()}** — that
     * method runs on a rebuilt object where every instance property is back at
     * its constructor default (9962, which names this class's `$paged` by
     * name). Nothing here may be asked there.
     */
    private ?Throwable $mailFailure = null;

    /**
     * @param  string  $occasion  The carrier's own message id for the inbound
     *                            text that matched, passed in rather than
     *                            derived — {@see AnswerAgentTurnJob::idempotencyKey()}
     *                            records the defect that made it a parameter
     *                            there, and this job keys both its run and its
     *                            send on the same string.
     * @param  list<string>  $terms  The tenant's own words that matched.
     *                               ⛔ **THE WORDS, NEVER THE MESSAGE.** The
     *                               customer's text is not on this payload and
     *                               may not be: `failed_jobs` has no row-level
     *                               security and no crypto-shred reaches it
     *                               (3192). A tenant's own configured vocabulary
     *                               is not personal data; the sentence a member
     *                               of the public wrote is.
     *                               ⚠️ **THIS SAID "NOTHING PRUNES IT" AND THAT
     *                               STOPPED BEING TRUE ON 2026-08-23**
     *                               (8610-8639): `jobs:prune-failed` gives that
     *                               table a thirty-day horizon. **The refusal is
     *                               unchanged and the correction is a sub-clause
     *                               rather than the sentence** — a horizon is
     *                               not an erasure path, so a data subject who
     *                               asks tomorrow still has anything of theirs
     *                               in a payload survive the request, and the
     *                               other two clauses were the load-bearing ones
     *                               anyway.
     *                               ✅ **AND THE ARM THAT DID NOT REFUSE HAS
     *                               STOPPED TOO** (8720): `AnswerAgentTurnJob`
     *                               carried the customer's sentence on exactly
     *                               this path, one branch over, under a docblock
     *                               saying it did not. It carries the row id
     *                               now. This paragraph was right first.
     */
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $conversationId,
        public readonly string $occasion,
        public readonly array $terms,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'agent.urgent_escalation';
    }

    /**
     * ⚠️ **KEYED ON THE INBOUND MESSAGE, NOT ON THE THREAD.** A thread escalates
     * once, so the conversation alone would look sufficient — and it would mean
     * that a customer who texts *"the boiler is leaking"*, gets no reply because
     * the owner had not yet resumed their paused account, and texts again an
     * hour later is met by this job's own spent claim. The occasion is the
     * carrier's message id, so a redelivery of one message collides and a second
     * message does not.
     */
    protected function idempotencyKey(): string
    {
        return 'agent-urgent:'.$this->conversationId.':'.$this->occasion;
    }

    /**
     * See the class docblock: held from the page, not from the send.
     */
    protected function claimIsSpent(): bool
    {
        return $this->paged;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->escalate();
    }

    /**
     * ⛔ **THE SAME WORK, AND RULE 44 DEMONSTRATED RATHER THAN EXCEPTED.**
     * `SendAgentNudgeJob` and `SendMissedCallTextBackJob` both answer this way
     * and for this reason: nothing on this path touches the Google Business
     * Profile API — the page is our own mailer, the acknowledgement is our own
     * texter, and no link is minted — so there is no reduced-capability version
     * to describe. The row gate is *"the whole review engine runs with zero GBP
     * API access"*, and this is a demonstration of it.
     *
     * ⛔ **THE FIRST DRAFT SPLIT THE TWO AND THAT WAS 256's SHAPE.** It paged
     * the owner and deliberately sent the customer nothing, which reads as a
     * considered degradation and is **unreachable**: this class does not
     * override {@see self::canExecute()}, so nothing can ever return false and
     * `handoff()` could never run. **A branch nothing reaches is not a
     * protection layer**, and a docblock claiming it is is what stops the next
     * reviewer looking (314–316). If a provider dependency is ever added here,
     * the split belongs back — with a test that can reach it.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->escalate();
    }

    /**
     * Page the owner, then answer the customer — in that order, always.
     *
     * @return array<string, mixed>
     */
    private function escalate(): array
    {
        $conversation = Conversation::query()->find($this->conversationId);

        if (! $conversation instanceof Conversation) {
            return ['paged' => false, 'reason' => 'thread_not_found'];
        }

        $page = $this->page();

        // ⛔ **THE CUSTOMER IS ANSWERED BEFORE ANYTHING IS RETHROWN, AND THAT
        // ORDERING IS THE POINT** (11141). A `deliverNow()` that throws where
        // it stands would take the owner's text and the customer's
        // acknowledgement with it — the two channels most likely to be working
        // on the day the mail transport is not — and leave somebody who texted
        // *"gas leak"* in silence from a thread whose assistant has already
        // gone quiet. Everything that can still be done is done first.
        $output = $page + [
            'terms_matched' => count($this->terms),
            // ⛔ COUNTS, REASONS AND HANDLES — NEVER THE WORDS AND NEVER THE
            // MESSAGE. `automation_runs.output` is operator-visible and
            // long-lived; an operator of this platform has no business reading
            // which of a tenant's customers said what.
            'acknowledged' => $this->acknowledge($conversation, $this->paged),
        ];

        if (! $this->paged && $this->mailFailure instanceof Throwable) {
            // ⛔ **ONLY WHERE NOBODY WAS REACHED — see the class docblock.** A
            // mail that failed beside a text that went is a degradation and
            // keeps its run row; a page that reached nothing at all is a
            // failure, and the ladder behind this throw is the only thing on
            // this path that ends in a bell naming the account.
            //
            // ⚠️ **THE ORIGINAL THROWABLE, NOT A SUBSTITUTE**, on
            // `NotifyOwnerOfVoicemailJob`'s precedent (10986): a transport's
            // own message is what tells an operator whether the fault is a
            // password, a port or a refused recipient, and
            // `AutopilotJob::handle()`'s `catch` is what puts it on the run
            // row. ⚠️ **So `automation_runs.error` can hold a vendor's verbatim
            // reply, which may quote the account holder's own address** — a
            // narrower exposure than the words rule above, because the address
            // is the tenant's own and the row is theirs, and a property of
            // every `AutopilotJob` rather than of this one. Raised at 11144.
            throw $this->mailFailure;
        }

        return $output;
    }

    /**
     * Mail the account holder, and text them if they have consented to it.
     * `SummariseClosedThreadJob::notifyOwner()`'s shape for the mail half, and
     * the same authorisation model there: platform mail to an *account
     * holder* about their own account, which {@see PlatformMailer} has a
     * method for.
     *
     * ⛔ **SO §2.2's "OWNER SMS+EMAIL PING" SHIPPED AS EMAIL ONLY, AND THIS
     * PARAGRAPH SAID THE MISSING HALF WAS A RULING RATHER THAN AN
     * OMISSION — CORRECTED 2026-08-27 (10540).** It read: *"There is no
     * unpermitted `send()` on the SMS side and its absence is argued at
     * length: an owner's mobile reached us through a consent record like
     * anybody else's, and `29` §2 and `24` make consent the condition for
     * texting anyone. Texting an account holder on a system event needs a
     * basis nobody has recorded."* **The owner was asked directly whether
     * that basis should exist, and answered yes** — express consent, captured
     * in the wizard or the account settings screen, stored as a
     * `consent_records`-shaped row like any other. {@see PlatformTexter} now
     * has a permitted send for exactly this: {@see PlatformTexter::sendToOwner()},
     * gated on {@see OwnerConsentService::permit()} refusing silently — never
     * throwing — for a business that has not consented, which is most of
     * them until this ships.
     *
     * ⚠️ **THE MAIL IS UNCONDITIONAL AND THE TEXT IS BEST-EFFORT, AND THAT
     * ASYMMETRY IS DELIBERATE.** The mail carries `claimIsSpent()` — it is
     * what makes this job's retry safe, on the account relationship
     * `PlatformMailer::send()` has always used. The text is additional and
     * strictly optional: a business with no owner-notify consent, an
     * exhausted number inventory, or a transport failure all degrade to
     * "the mail still went", never to "the page failed" — `29` §2 rule 43's
     * surviving half, graceful degradation, never hard-fail.
     *
     * ⚠️ **THE SEND IS NOT DEBITED AGAINST THE TENANT'S SMS CREDIT GRANT,
     * AND THAT IS A JUDGEMENT CALL RECORDED RATHER THAN MADE QUIETLY**
     * (10540). The 500 monthly messages a tenant buys are priced for review
     * invites, missed-call text-back and the chat bot (`CLAUDE.md` §Commercial
     * model's credits table) — not for the platform notifying them about their
     * own account, which is the same category `PlatformMailer::send()`'s
     * unpermitted account-holder mail already treats as a platform operating
     * cost rather than a tenant-metered one. What bounds the cost is not a
     * ledger debit but the same thing that already bounds the email twin:
     * this job's own `claimIsSpent()`, held from the page, so a retry cannot
     * re-page — and re-text — twice, and the event itself is rare by
     * construction (`UrgentTerms::MAX_TERMS` bounds how many words a tenant
     * may even mark urgent). The alternative — debiting `SendCredits` like a
     * customer-facing send — is equally defensible and is the owner's to
     * choose if this is revisited; recorded so the choice is visible rather
     * than assumed.
     *
     * ⛔ **THE ASYMMETRY PARAGRAPH ABOVE IS SUPERSEDED IN ONE CLAUSE AND KEPT
     * FOR THE REST — 11140.** *"The mail is unconditional"* is still true; *"a
     * transport failure … degrades to «the mail still went»"* was written about
     * the SMS half and read, wrongly, as though the mail half could not fail at
     * all. It could and did, silently. **What decides whether the page
     * succeeded is no longer the mail but whether either channel reached the
     * account holder**, and the text is no longer conditional on the mail
     * having been attempted at all.
     *
     * @return array{paged: bool, mailed: bool, texted: bool, mail_refusal: ?string}
     */
    private function page(): array
    {
        $owner = Business::query()->find(Tenancy::idOrFail())?->owner;

        $address = $owner instanceof User ? trim((string) $owner->email) : '';

        $mailed = false;

        if ($address !== '') {
            try {
                // ⛔ `deliverNow()` RATHER THAN `send()`, AND THE THREE THINGS
                // BELOW IT ARE WHY — 11140. `send()` swallows every throwable
                // and returns `void` (9500), so all three ran for a message
                // the transport never accepted: a flag that permanently spends
                // this job's claim, a run row saying `paged`, and a sentence
                // to a member of the public saying the business had been told.
                app(PlatformMailer::class)->deliverNow($address, new UrgentMessageEscalated($this->terms));

                $mailed = true;
            } catch (Throwable $e) {
                // ⚠️ **`Throwable` AND NOT `MailNotDeliverable`, AND THE
                // COMMENT IS ABOUT THE WHOLE SET.** Both populations reach
                // here and both matter: a `MailNotDeliverable` is our own
                // configuration refusal, and a Symfony `TransportException`
                // from a refused connection or a wrong password is the
                // commonest real fault on this path and is the one
                // `DeliverPlatformMail::handle()`'s narrower `catch` has never
                // seen. **Nothing is swallowed** — this is a hold, so the text
                // and the customer's answer can still be attempted, and
                // {@see self::escalate()} decides what to do with it after.
                $this->mailFailure = $e;
            }
        }

        // ⛔ **ATTEMPTED WHATEVER THE MAIL DID, AND THAT IS A CHANGE** (11142).
        // It used to run only after a `send()` that could not fail, so it was
        // unreachable for a business whose owner row carries no address at
        // all — and on the day the mail transport is down, the owner channel
        // is the one that might still work. **A bell whose only clapper is the
        // mail system cannot ring about the mail system** (9377).
        $texted = $this->textOwner();

        // From here a retry would page the owner twice — see `claimIsSpent()`.
        // ⛔ **THE UNION, AND NEVER `$mailed` ALONE.** `claimIsSpent()` is what
        // stops a retry re-paging, so keying it on the mail alone would re-text
        // an owner who had already been texted; keying it on the text alone
        // would re-mail one who had already been mailed.
        $this->paged = $mailed || $texted;

        return [
            'paged' => $this->paged,
            'mailed' => $mailed,
            'texted' => $texted,
            // ⛔ **THE CLASS, NEVER THE MESSAGE.** A transport's reply is
            // written by somebody else's server and its ordinary content is a
            // recipient address — `DeliverPlatformMail::failed()`'s *"ours or
            // nothing"* rule, on the surface an operator actually reads.
            'mail_refusal' => $this->mailFailure === null ? null : $this->mailFailure::class,
        ];
    }

    /**
     * The SMS half of the page, best-effort — see {@see self::page()}'s own
     * docblock for why this never decides whether the page succeeded.
     *
     * ✅ **AND SINCE WAVE 40 LANE A THE SEND LEAVES A RECORD** (10820).
     * {@see PlatformTexter::sendToOwner()} writes an `owner_notifications` row
     * naming {@see OwnerNotificationKind::UrgentEscalation} and this job's own
     * occasion, so *"we texted the owner about this"* is answerable afterwards
     * — and an owner's reply, which wave 39 lane C made storable, finally has
     * something it can be recorded as an answer to. ⚠️ **The record is not a
     * delivery**: no owner-channel delivery receipt is read anywhere, and the
     * mail remains the half that decides whether the page succeeded.
     *
     * ⛔ **THAT LAST CLAUSE IS SUPERSEDED — 11140.** The mail no longer decides
     * on its own: {@see self::page()} takes the union, so an owner reached only
     * here is an owner who was paged, and this method's answer is now read.
     * ⚠️ **What is unchanged is that the record is not a delivery** — no
     * owner-channel delivery receipt is read anywhere, so `true` from here
     * means *a carrier accepted the message*, exactly as `deliverNow()`
     * returning means *a transport accepted it*.
     *
     * @return bool whether a carrier took the message — never whether it arrived
     */
    private function textOwner(): bool
    {
        $business = Business::query()->find(Tenancy::idOrFail());

        if (! $business instanceof Business) {
            return false;
        }

        $permit = app(OwnerConsentService::class)->permit($business);

        if ($permit === null) {
            // No owner-notify consent on file, or the owner has said STOP.
            // Silent, `sendToOwner()`'s own refusal shape.
            return false;
        }

        try {
            $sent = app(PlatformTexter::class)->sendToOwner(
                $permit,
                $this->smsBody(),
                // ⛔ **THE OCCASION IS THE CARRIER'S INBOUND MESSAGE ID, THE
                // SAME STRING THIS JOB KEYS ITS RUN AND ITS CUSTOMER
                // ACKNOWLEDGEMENT ON** (10820). A value derived here would
                // differ on every attempt, which is the defect
                // `AnswerAgentTurnJob::idempotencyKey()` records — and it
                // would also make a retried page look like a page about a
                // second, imaginary event rather than a second page about one.
                OwnerNotificationKind::UrgentEscalation,
                $this->occasion,
            );
        } catch (TextNotDeliverable $e) {
            // ⚠️ **SWALLOWED, NEVER RETHROWN.** A transport failure on this
            // channel must not turn a page the mail carried into a failed job
            // whose retry re-mails the owner (`claimIsSpent()`'s own reasoning,
            // one channel over). ⛔ **AND IT IS NOT LOST**: `false` from here
            // is what {@see self::escalate()} reads to decide that nobody was
            // reached at all, so a failure on **both** channels now throws
            // rather than closing `Succeeded`.
            //
            // ⚠️ **THIS SENTENCE SAID *"THE EMAIL PAGE STILL WENT"* AND COULD
            // NOT KNOW — 11140.** It was written under a `send()` that returned
            // `void` whatever happened.
            Log::warning('An urgent-escalation text to the owner could not be sent.', [
                'reason' => $e->getMessage(),
                'business_id' => $business->getKey(),
            ]);

            return false;
        }

        // ⚠️ **NULL IS A REFUSAL AND NOT A SEND** — `sendToOwner()` answers it
        // for a halted channel and for a number inventory with nothing able to
        // send, and both are *"the owner was not texted"*.
        return $sent instanceof SentText;
    }

    /**
     * The words that matched, never the customer's message —
     * {@see UrgentMessageEscalated}'s own rule, carried onto the shorter
     * channel. Kept to one segment: GSM-7, no em dash, well under 159
     * characters even with a long tenant-chosen word.
     */
    private function smsBody(): string
    {
        $term = $this->terms[0] ?? 'one of your urgent words';

        return "GO AI EZ: Something urgent came in - a customer mentioned '{$term}'. "
            .'Your assistant stopped answering; check your inbox to reply. Reply STOP to opt out.';
    }

    /**
     * Tell the person who texted, or say why not.
     *
     * ⛔ **THERE IS NO URGENCY EXEMPTION FROM THE SEND GATE AND THIS JOB DOES
     * NOT INVENT ONE.** {@see ConsentService::decide()} answers, a
     * {@see SendPermit} is required to build the message at all, and a
     * suppressed, opted-out or unknown recipient is refused here exactly as they
     * are everywhere else. **Whether urgency should override a quiet window is a
     * ruling and not this job's**, and it does not arise today: the purpose is
     * `Transactional`, which the T69 law already exempts from recipient-local
     * quiet hours because the recipient sent the previous message.
     *
     * ⚠️ **A CONTACT WITH NO CONSENT RECORD GETS NOTHING, WHICH IS MOST OF THEM
     * TODAY** — 3102's open question, unchanged by this slice and inherited from
     * {@see AnswerAgentTurnJob} rather than introduced here. The owner is paged
     * regardless, which is the half that matters and the reason the page runs
     * first.
     *
     * @param  bool  $ownerReached  Whether {@see self::page()} actually got the
     *                              account holder on a channel — mail or text.
     *                              ⛔ **IT DECIDES THE WORDS, AND THAT IS THE
     *                              WHOLE OF 11141.** Passing it rather than
     *                              re-reading `$this->paged` here is what makes
     *                              the dependency visible at the call site; the
     *                              two are the same value and this is the one
     *                              place it is allowed to matter.
     * @return array<string, mixed>
     */
    private function acknowledge(Conversation $conversation, bool $ownerReached): array
    {
        $customerId = $conversation->customer_id;

        $customer = $customerId === null
            ? null
            : Customer::query()->find($customerId);

        if (! $customer instanceof Customer) {
            // ⛔ **NOBODY TO ADDRESS, AND NO CONTACT IS CREATED HERE** —
            // `AnswerAgentTurnJob::send()`'s refusal, for its reason. A thread
            // with no contact is the ordinary state for a first-time texter.
            return ['sent' => false, 'reason' => SendRefusalReason::NoIdentifier->value];
        }

        $decision = app(ConsentService::class)->decide(
            $customer,
            OutreachChannel::Sms,
            // ⛔ **THE T69 LAW LIVES ON THIS ARGUMENT** — the same one
            // `AnswerAgentTurnJob` and `MissedCallTextBack` make. `Marketing`
            // here would hold the reply to an urgent message behind
            // recipient-local quiet hours, which is the one message that must
            // never wait for morning. A test mutates this line.
            OutreachPurpose::Transactional,
        );

        $permit = $decision->permit;

        if (! $permit instanceof SendPermit) {
            return [
                'sent' => false,
                'reason' => ($decision->reason ?? SendRefusalReason::NoConsentRecord)->value,
            ];
        }

        $outcome = app(MessageSender::class)->send(OutboundMessage::for(
            permit: $permit,
            body: $this->compose($ownerReached),
            // Keyed on the same occasion as the run, so a redelivery meets
            // `outreach_messages`' unique index, the sender answers `Duplicate`
            // and nothing is debited twice.
            key: SendKey::for($permit, 'agent-urgent:'.$conversation->getKey().':'.$this->occasion),
            purpose: OutreachPurpose::Transactional,
        ));

        return [
            'sent' => $outcome->wasSent(),
            'send_key' => $outcome->key->value,
            // The sender's own verdict beside ours — `AnswerAgentTurnJob`'s
            // note: `sent: false` with no reason sends an operator to raise a
            // ticket about the wrong thing.
            'status' => $outcome->status->value,
            'refusal' => $outcome->reason?->value,
        ];
    }

    /**
     * The words a person receives after texting one of this business's urgent
     * words.
     *
     * ⛔ **IT DISCLOSES, AND UNCONDITIONALLY.** §2.1's disclosure rides *the
     * first agent turn of a thread*, and {@see AgentComposer::withDisclosure()}
     * takes that from the turn count — but this message is not a turn and no
     * turn count moves for it, so there is no reading of the count that is
     * honest here. `MissedCallTextBack::compose()` settles the shape of that
     * trade: **one redundant true sentence is the affordable side, and a missing
     * required one is not.** A disclosure law has no urgency exemption, and P19
     * (4193) is the precedent that a *template* still owes it.
     *
     * ⛔ **AND IT PROMISES NOTHING RAIL 5 FORBIDS.** No arrival time, no
     * window, no *"someone is on their way"*: the two facts stated are that the
     * owner has been told and that the assistant has stopped, and both are true
     * by the time this leaves — {@see AgentTurns} escalated the thread before
     * this job existed and {@see self::page()} ran before this line.
     *
     * ## ⛔ *"BOTH ARE TRUE"* WAS CHECKED AGAINST A PROGRAM COUNTER — 11141
     *
     * ⛔ **THE PARAGRAPH ABOVE IS KEPT VERBATIM BECAUSE ITS LAST CLAUSE IS THE
     * DEFECT.** *"`page()` ran before this line"* is not *"the owner was
     * told"*, and under {@see PlatformMailer::send()} the two came apart
     * silently: with the mail ceiling unstated, nothing was sent, `$paged` was
     * `true`, and this sentence went to a member of the public who had just
     * texted the word *"gas leak"*. **A docblock that reasons from a method
     * having executed cannot vary with whether the message was accepted**,
     * which is the shape it is watching for everywhere else.
     *
     * ✅ **SO THERE ARE TWO SENTENCES AND THE FACT CHOOSES BETWEEN THEM.** When
     * the account holder was reached, the message says so; when they were not,
     * it says only what is unconditionally true — the assistant has stopped,
     * the thread is flagged urgent and waiting for a person, and here is the
     * emergency line if the tenant set one.
     *
     * ⛔ **ONE UNCONDITIONAL SENTENCE FOR EVERYBODY WAS THE OTHER OPTION AND IS
     * REFUSED, WITH THE ARGUMENT FOR IT WRITTEN DOWN.** It is simpler, always
     * true, and has no branch to get wrong. It is refused for two reasons.
     * First it charges the reassurance of every correctly paged customer to a
     * fault almost none of them hit — on the one message where reassurance is
     * the product. Second, and the load-bearing one: **a sentence that cannot
     * vary with whether the page worked makes the page's success unobservable
     * from the thing the customer receives**, which is the same defect as the
     * docblock this paragraph replaces, moved one layer out.
     *
     * ⚠️ **NEITHER SENTENCE NAMES A TIME, AND THE WEAKER ONE DELIBERATELY DOES
     * NOT SAY *"WILL"*.** *"A person will read it"* is a claim about somebody
     * else's behaviour that this system can only support when it has actually
     * told them.
     *
     * @param  bool  $ownerReached  see {@see self::acknowledge()}
     *
     * ⚠️ **THE EMERGENCY LINE IS GIVEN ONLY WHEN THE TENANT SET ONE, AND IS
     * NEVER INVENTED** — `UrgentTerms::emergencyLine()`'s own rule: *"null is an
     * answer and not a failure … there is no number that is right for a business
     * which has not given us one."* It is normalised on the way in by
     * {@see UrgentTerms::setEmergencyLine()}, so what is read out here is a
     * number this application could parse.
     *
     * ⛔ **AND IT IS NOT "CALL 911".** Skill 9's *"life-safety → advise emergency
     * services"* is P4's, unconditional, and lives in the composer's standing
     * instructions. Saying it here would say it for *lockout* and *invoice
     * overdue* as readily as for *gas leak*, which is the alert that gets
     * ignored, on the sentence that must not be.
     */
    private function compose(bool $ownerReached): string
    {
        $business = $this->businessName();

        $body = $ownerReached
            ? "This is {$business}'s AI assistant. Thanks — I have passed this straight to {$business} "
                .'as urgent and stopped answering, so a person will read it and reply to you here.'
            // ⛔ **EVERY CLAUSE HERE IS ABOUT THIS SYSTEM AND NOT ABOUT THE
            // BUSINESS.** The thread really is `Escalated` — `AgentTurns`
            // wrote that synchronously, before this job existed — so it really
            // is marked urgent and really is waiting on a person, and it is on
            // the tenant's own inbox with an activity row beside it. What is
            // *not* claimed is that anybody has been told, because nobody has.
            : "This is {$business}'s AI assistant. Thanks — I have marked this urgent and stopped "
                ."answering, so it is waiting for a person at {$business} to read and reply to you here.";

        $line = app(UrgentTerms::class)->emergencyLine();

        if ($line !== null) {
            $body .= " If you need someone sooner, call {$line}.";
        }

        return $body;
    }

    /**
     * `SendAgentNudgeJob::businessName()`'s read and its fallback.
     */
    private function businessName(): string
    {
        $name = trim((string) (Business::query()->find(Tenancy::idOrFail())->name ?? ''));

        return $name === '' ? 'us' : $name;
    }

    /**
     * ⛔ **SILENCE, BECAUSE {@see AgentThreadStates::escalate()} ALREADY WROTE
     * THE FEED ITEM.** It records `AssistantHandedOver` for this very
     * escalation, and returning an action here would put two entries in the feed
     * for one event — `AnswerAgentTurnJob::activityAction()`'s argument, for its
     * reason, one lane over. The owner-facing account of *why* it was handed
     * over is {@see UrgentMessageEscalated}, which is a mail rather than a feed
     * row precisely because it has to arrive rather than be scrolled to.
     * ⚠️ **THE CASE NAMED ABOVE IS NO LONGER THE ONE WRITTEN — CORRECTED
     * 2026-08-22 (7420).** `AgentThreadStates::escalate()` now files
     * `AssistantAskedYouToTakeOver` (7340–7354). **The silence argued here is
     * unchanged and is why this paragraph stays** — one event, one row.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    /**
     * ⛔ THE THREAD AND THE COUNT — never the words and never the message.
     * `AgentSkill::UrgentEscalation` is named so an operator reading a run row
     * can tell which skill produced it without reading the customer's text.
     *
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return parent::input() + [
            'conversation_id' => $this->conversationId,
            'skill' => AgentSkill::UrgentEscalation->value,
            'terms_matched' => count($this->terms),
        ];
    }
}
