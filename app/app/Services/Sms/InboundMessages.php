<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\AutopilotActionType;
use App\Enums\InboundKeyword;
use App\Enums\LiftSource;
use App\Enums\MessageCostKind;
use App\Enums\OutreachChannel;
use App\Enums\SuppressionReason;
use App\Models\Conversation;
use App\Models\InboundMessage;
use App\Models\OwnerReply;
use App\Services\ActivityService;
use App\Services\Agent\AgentTurns;
use App\Services\AuditService;
use App\Services\Billing\MessageCostLedger;
use App\Services\Billing\MessageRates;
use App\Services\Campaigns\CampaignReplies;
use App\Services\Consent\ConsentService;
use App\Services\Consent\OwnerConsentService;
use App\Services\Conversations\FiledInboundMessage;
use App\Services\Conversations\InboundThreading;
use App\Services\Messaging\SendingHealth;
use App\Support\Identifier;
use App\Support\SqlState;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What happens when a customer texts us back — row 4 slice 2.
 *
 * ⚠️ **THIS IS THE FILE THAT MAKES `sms.enabled` FLIPPABLE, AND THE RULE IT
 * SATISFIES IS THE SHORTEST ONE IN THE ROW: you may not send what you cannot
 * stop** (1567). Until this existed, `opt_outs` had no writer from a real
 * channel — decision 272's shape sitting on the one table where being
 * writerless is a legal exposure rather than an inert feature.
 *
 * ⚠️ **AN INBOUND STOP HAS NO TENANT, AND EVERYTHING BELOW FOLLOWS FROM THAT.**
 * The webhook carries a sender, our receiving number and a body. Nothing in it
 * names a business: the one number this platform sends from is the shared Lane A
 * pool number, which belongs to nobody by design. So this writes the
 * **platform-scoped**
 * refusal and never the tenant-scoped one — which is not a compromise but the
 * correct reading, and `ConsentService::registerOptOut()`'s own docblock said so
 * before this file existed: *"Lane A's shared number is what a STOP arrives on
 * when we do not know better, and over-suppressing costs a message that was not
 * sent while under-suppressing costs a message to somebody who said STOP."*
 *
 * A platform-scoped opt-out is also the **stronger** record. `ConsentService::
 * isSuppressed()` asks `hasOptedOut()` first, before the tenant list, so one row
 * here refuses every send to that person on every tenant — which is exactly what
 * a carrier STOP on a shared number means.
 *
 * ⚠️ **THE SCOPE IS NOT A PARAMETER ANYWHERE ON THIS PATH, AND THAT IS THE
 * POINT.** `suppressFromCarrier()` and `liftFromCarrier()` take no scope at all;
 * platform is the only thing they can write. An earlier draft of this class held
 * it as a constant and passed it down, which reads as configurable and is one
 * argument away from being made so.
 *
 * ⛔ **SLICE 6 IS THE DAY 1582 WARNED ABOUT, AND THE ANSWER IS STILL NO.** That
 * decision predicted this exactly: *"the day slice 6 resolves a number to a
 * business, the tempting change is to narrow the refusal to that tenant — and it
 * would be wrong on Lane A, where the number is shared and the carrier's STOP is
 * keyed on the number rather than on whoever prompted the message."* Phase 1 now
 * records `to_number`, so the resolution is one join away and looks like an
 * obvious improvement. **It is not taken.** The number this platform sends from
 * today is the shared Lane A pool number: narrowing a STOP that arrived on it to
 * whichever tenant last messaged that person would leave every *other* tenant
 * free to text somebody who told the carrier to stop, and the failure would be
 * invisible — the suppression exists, the log says it was honoured, and the next
 * send goes out. Narrowing needs its own decision, at the write site, and it is
 * the tenant-owned Lane B number that could ever justify one.
 *
 * ## Three refusals that are not errors
 *
 * **A replay.** The carrier retries a webhook that did not answer promptly, so a
 * redelivery is ordinary. `inbound_messages.provider_message_id` is unique and
 * the *database* refuses the second one — not an `->exists()` check, which two
 * concurrent redeliveries both pass.
 *
 * **An unparseable sender** (425). A number that will not normalise cannot be
 * hashed, and a hash matches only exactly — so a suppression written against an
 * unmatchable value would refuse nothing while reporting success. It **fails
 * closed**: nothing is written, and the failure is recorded — in the *log*
 * rather than `audit_log`, because `AuditService::record()` needs a tenant this
 * path does not have. Stated precisely rather than as "it is audited", which is
 * the claim-before-it-is-true shape `CLAUDE.md` records three times.
 *
 * **A keyword we do not act on.** Most inbound text is somebody replying to us
 * in words. It is recorded as `None`, and since P18 it is also **threaded into
 * the tenant's Inbox** where a person can answer it — see
 * {@see InboundThreading}, which refuses far more often than it acts and says
 * why. ⚠️ **That is a refusal rather than a keyword branch**: nothing about the
 * suppression, the scope or the compliance replies above changes, and the STOP,
 * START and HELP arms deliberately do not thread.
 *
 * ✅ **THAT LAST SENTENCE STOPPED BEING TRUE AT P20 AND IS KEPT BECAUSE THE
 * REFUSAL IS.** *"Nothing else happens"* was right about the compliance
 * branches and is still right about them — an unrecognised keyword writes no
 * suppression and sends no reply. What now also happens is
 * {@see self::linkToCampaign()}, which files the reply against the send it
 * answers (R20) and touches none of the above: it runs last, it runs for every
 * keyword, and it swallows its own failure.
 *
 * ✅ **AND SINCE P10 THERE IS A SECOND SUCH STEP, ON EXACTLY THOSE TERMS.**
 * {@see self::captureMedia()} keeps the pictures on an MMS. It runs after the
 * linkage — later still, because it dispatches a job as well as writing a row —
 * it runs for every keyword, and it swallows its own failure. ⛔ **The one thing
 * it does that nothing else on this path does is decide NOT to keep something**:
 * a PHI tenant's inbound picture is refused before a socket opens (4166), and
 * the refusal is a row rather than a silence. ⚠️ **THERE ARE TWO SUCH
 * REFUSALS SINCE WAVE 41 LANE E** (11100): the second is a photograph from this
 * business's own account holder after they have said STOP, which is 10830's
 * ruling applied to the other half of the same message.
 *
 * ✅ **AND SINCE 4540 THERE IS A THIRD, WHICH IS THE ONE THAT ANSWERS THE
 * CUSTOMER.** {@see self::answerWithAssistant()} hands the thread to
 * {@see AgentTurns}, which is the dispatch the whole agent engine was built
 * without — P3, P4, P13, P18 and P19 shipped and **nothing in `app/` dispatched
 * any of them** (4534). It is last of the four for `captureMedia()`'s reason one
 * step further on: it dispatches a job that *spends money and texts a member of
 * the public*, so everything that must land whatever happens — the suppression,
 * the `inbound_messages` row, the campaign linkage, the pictures — lands first.
 * ⛔ **It runs on the `None` arm alone**, unlike the other two: STOP, START and
 * HELP are compliance instructions with carrier-required replies, and answering
 * a withdrawal of consent conversationally is the one reply that must never be
 * sent. {@see InboundThreading} already refuses to thread them, so the thread it
 * returns is the whole population.
 *
 * ## The complaint counter is attributed to a tenant; the refusal still is not
 *
 * ⛔ **THIS IS THE SECOND OF 2499'S TWO OWED WRITERS AND IT LANDS ON A PATH
 * WHOSE WHOLE DESIGN IS "NO TENANT".** {@see SendingHealth} had no caller
 * anywhere in `app/` until 2026-08-12 (2496), so the per-tenant pause and the
 * automatic platform halt that 2102 requires — and 2113 makes a precondition of
 * sending at all — read a complaint rate that was permanently zero. A STOP text
 * is the only complaint signal this platform has (there is no carrier feedback
 * loop; that service's own docblock says so), which puts the writer here.
 *
 * ⚠️ **ATTRIBUTING THE COUNT IS NOT NARROWING THE REFUSAL, AND CONFLATING THE
 * TWO WOULD UNDO 1582.** The paragraph above is unchanged and stays unchanged:
 * the suppression is written **platform-wide**, through a method that takes no
 * scope at all, because a carrier STOP is keyed on the number and the recipient
 * rather than on whoever prompted the message. The counter answers a different
 * question — *whose sending produced this* — and that question has to have a
 * tenant in it or the per-tenant containment is not per-tenant. **One STOP
 * therefore refuses every tenant's next send and increments exactly one
 * tenant's counter.**
 *
 * ⚠️ **THE TENANT IS A LOOKUP ON OUR OWN NUMBER, NEVER AN INFERENCE FROM THE
 * SENDER'S HISTORY.** {@see TenantNumbers::tenantFor()} is dedicated number reverse lookup
 * and 2125's precedent: a tenant inferred from *"who last messaged this
 * person"* is wrong the first time somebody is a customer of two tenants, and
 * here being wrong means charging one tenant with another's complaint and
 * pausing the innocent one.
 *
 * ⛔ **SO A STOP ON THE SHARED POOL NUMBER IS COUNTED NOWHERE, AND THAT IS A
 * REAL GAP RATHER THAN A TIDY DEFAULT.** `tenantFor()` answers null for a number
 * nobody owns; the refusal is still honoured platform-wide, but no counter
 * moves. Until every sending tenant holds their own number under dedicated number allocation, the
 * complaint rate under-reports — and an under-reporting complaint rate is a
 * containment that does not fire. It is written here rather than left for
 * somebody to discover from a flat graph.
 */
final class InboundMessages
{
    /** Who the record names for an action nobody in this company took. */
    private const string ACTOR = 'carrier:infobip';

    public function __construct(
        private readonly ConsentService $consent,
        private readonly ComplianceReplies $replies,
        private readonly TenantNumbers $numbers,
        private readonly SendingHealth $health,
        private readonly InboundThreading $threading,
        private readonly CampaignReplies $campaigns,
        private readonly InboundMediaCapture $media,
        private readonly AgentTurns $agent,
        private readonly OwnerConsentService $owner,
        private readonly OwnerNotifications $notifications = new OwnerNotifications,
        private readonly MessageCostLedger $costs = new MessageCostLedger,
        private readonly MessageRates $rates = new MessageRates,
        private readonly ActivityService $activity = new ActivityService,
        private readonly AuditService $audit = new AuditService,
    ) {}

    /**
     * Handle one inbound message, exactly once.
     *
     * Returns what we decided it was, or **null when this message has already
     * been handled** — which the caller answers 200 to, because the carrier did
     * its job and we had already done ours.
     *
     * @param  list<InboundMediaPart>  $media  The pictures on an MMS, if any
     *                                         (T176 P10). ⚠️ **Last, optional
     *                                         and empty by default**, which is
     *                                         the honest shape: an SMS has none,
     *                                         and every existing caller and test
     *                                         is an SMS. It changes nothing
     *                                         above it — see
     *                                         {@see self::captureMedia()} for
     *                                         where it runs and why that is the
     *                                         only place it may.
     */
    public function handle(
        string $providerMessageId,
        string $from,
        ?string $text,
        ?string $receivedAt = null,
        ?string $toNumber = null,
        array $media = [],
        ?int $segments = null,
    ): ?InboundKeyword {
        $keyword = InboundKeyword::parse($text);

        // ⚠️ NORMALISED AND HASHED BEFORE ANYTHING IS WRITTEN, because a sender
        // we cannot hash is a sender we cannot suppress, and finding that out
        // *after* recording the message would leave a row saying we received a
        // STOP beside no refusal honouring it.
        $hash = Identifier::hash($from, OutreachChannel::Sms);

        if ($hash === null) {
            // 425's case, failing closed and loudly. Nothing is recorded in
            // `inbound_messages` either: the row's own key is the hash, so there
            // is nothing to record it against, and a row with a placeholder hash
            // would be indistinguishable from a real one at a glance.
            //
            // ⚠️ **THE LOG RATHER THAN `audit_log`, AND NOT BY PREFERENCE.**
            // `AuditService::record()` opens with `Tenancy::idOrFail()`, and
            // this path has no tenant to establish — the same fact that decides
            // the scope above. An audit entry is the right home for this and it
            // cannot be written from here; recording it in the log is the honest
            // second-best rather than a silent drop.
            //
            // ⚠️ THE NUMBER IS DELIBERATELY ABSENT. It is unhashable, not
            // unreadable, so writing it here would put the one identifier we
            // could not protect into the one place we could not scrub. The
            // carrier's message id is enough to find it in Infobip's own logs.
            Log::warning('An inbound message could not be honoured: its sender does not normalise.', [
                'provider_message_id' => $providerMessageId,
                'keyword' => $keyword->value,
                'actor' => self::ACTOR,
            ]);

            return null;
        }

        $record = $this->record($providerMessageId, $hash, $keyword, $receivedAt, $toNumber);

        if ($record === null) {
            // A replay. Already handled, and deliberately handled no further —
            // re-running the branches below would send a second HELP reply and
            // write a second audit entry for one instruction.
            return null;
        }

        // ⚠️ **THE THREAD IS CARRIED FROM ONE STEP TO THE NEXT RATHER THAN
        // LOOKED UP AGAIN** (4236). `campaign_replies.conversation_id` is what
        // lets the Inbox read a reply in context, and this is the one moment
        // both halves are in hand — the thread has just been opened and the
        // linkage has not yet been written. Re-deriving it below from the
        // contact would be the `business_id + customer_id` guess that shows one
        // thread another thread's campaign; the migration that added the column
        // argues it at length.
        $filed = null;

        // ⛔ **10540, PHASE 3 — THE SHARPEST EDGE IN THE OWNER CHANNEL.** The
        // moment an owner can be texted (10540, phase 2), they can reply, and
        // before this a reply from that exact number was answered by the AI
        // bot as though it were a stranger's. `businessesFor()` is a reverse
        // lookup on the SENDER alone, never on `$toNumber` — an owner-channel
        // send may leave from a tenant's own Lane B number or, more often
        // today, the shared Lane A pool (`NumberSelector::forSending()`'s own
        // docblock: there is exactly one number in this platform's inventory),
        // so a lookup keyed on `$toNumber` would miss most of the population
        // this phase exists to protect.
        //
        // ⚠️ **A LIST, BECAUSE THE SAME PHONE CAN GENUINELY NAME MORE THAN ONE
        // BUSINESS** — see {@see OwnerConsentService::businessesFor()}. STOP
        // and START resolve every match, because ambiguity here should widen
        // suppression, never narrow it. Ordinary text below resolves only the
        // UNAMBIGUOUS case, for the opposite reason: guessing which of two
        // businesses a reply belongs to is the misrouting this phase exists to
        // stop, not a smaller version of it.
        $ownerBusinessIds = $this->owner->businessesFor($from);

        match ($keyword) {
            InboundKeyword::Stop => $this->stop($from, $toNumber, $ownerBusinessIds),
            InboundKeyword::Start => $this->start($from, $ownerBusinessIds),
            // ⚠️ HELP DOES NOT OPT ANYBODY OUT, and §2.10.4 names that as its own
            // test. Somebody asking what this is has not asked us to stop, and
            // conflating the two would suppress every curious recipient.
            //
            // ✅ **IT NOW REPLIES, AND DEDICATED NUMBER ALLOCATION IS WHAT UNBLOCKED
            // IT** (2125). This comment used to say the reply *"would have to
            // name the tenant — which is the thing this path cannot resolve"*,
            // and that was true: an inbound message arrives with no tenant, and
            // nothing in the payload identified one. One Infobip number per
            // tenant makes `$toNumber` identify exactly one business, so
            // `TenantNumbers::tenantFor()` answers it with a lookup rather than
            // an inference. ⛔ **A registered 10DLC campaign requires a working
            // HELP response and carriers test it**, so this is owed before the
            // submission rather than after it.
            //
            // ⚠️ **THE REPLY IS DELIBERATELY NOT GATED ON CONSENT** (2099):
            // STOP, HELP and suppression are unconditional under every recorded
            // basis, and somebody with no consent record is exactly who is most
            // likely to text HELP asking who we are.
            InboundKeyword::Help => $this->replies->answerHelp($from, $toNumber),
            // ✅ **AND ORDINARY TEXT NOW REACHES A HUMAN, WHICH IS P18's WRITER
            // DECISION** (4111). This arm read `null` — *"most inbound text is
            // somebody replying to a review invite in words. It is recorded as
            // `None` and nothing else happens"* — and nothing else did: the
            // words were dropped and the tenant never learned their customer had
            // answered. `conversations` and `messages` have existed since
            // 2026-07-30 with **no writer anywhere in `app/`**, so the Inbox
            // R21 calls the tenant's daily surface would have rendered an empty
            // screen for every tenant while every test of it passed.
            //
            // ⛔ **IT THREADS ONLY WHERE THERE IS A TENANT TO THREAD FOR**, and
            // on the shared Lane A pool number there is not — see
            // {@see InboundThreading} for why that may never be inferred, and
            // for the provisioning gap it leaves. Nothing in this class's own
            // scope decision changes: the suppression above is still written
            // platform-wide by a method that takes no scope at all.
            //
            // ⛔ **AND NOW IT DOES NOT THREAD AN IDENTIFIED OWNER EITHER**
            // (10540 phase 3). `count($ownerBusinessIds) === 1` is the
            // unambiguous case; `$filed` stays null exactly as it does for
            // STOP, START and HELP, so `linkToCampaign()` and
            // `answerWithAssistant()` both no-op — an owner's own reply is
            // never filed into a stranger's conversation and never answered
            // by the AI bot.
            //
            // ✅ **AND SINCE WAVE 39 LANE C IT IS NOT DISCARDED EITHER**
            // (row 10720+). `recordOwnerReply()` runs instead of nothing: the
            // reply is written to `owner_replies` — a tenant-owned table this
            // subject can carry, unlike the platform-scoped `inbound_messages`
            // — and a row is filed to the owner's own activity feed so the
            // one acknowledgement this platform gives them is that the words
            // arrived. **Nothing downstream reads them yet** — no automation
            // resumes, no `AgentTurns` call, nothing — which is stated here
            // rather than implied, because a stored reply reads as more than
            // it is until somebody says otherwise.
            InboundKeyword::None => $filed = count($ownerBusinessIds) === 1
                ? $this->recordOwnerReply($ownerBusinessIds[0], $providerMessageId, $text, $receivedAt)
                : $this->threading->thread($from, $toNumber, $text, $providerMessageId),
        };

        $this->linkToCampaign($record, $filed?->thread);

        $this->captureMedia($record, $media, $ownerBusinessIds);

        $this->recordCost($record, $providerMessageId, $toNumber, $media !== [], $segments);

        // G20-05: the inbound text becomes a module event so C-Reviews can hear a CSAT answer.
        $businessId = $filed?->thread->business_id ?? $ownerBusinessIds[0] ?? $this->numbers->tenantFor($toNumber);
        if ($businessId !== null) {
            \Illuminate\Support\Facades\Event::dispatch(new \App\Modules\CSms\Events\MessageReceived(
                businessId: (int) $businessId,
                personId: $filed?->thread->customer_id,
                fromPhone: $from,
                body: $text,
                providerMessageId: $providerMessageId,
                receivedAt: $receivedAt ?? now()->toIso8601String()
            ));
        }

        $this->answerWithAssistant($filed, $providerMessageId, $media !== []);

        return $keyword;
    }

    /**
     * Let the assistant answer it — T176 §2, decision 4534.
     *
     * ⛔ **LAST OF THE FOUR, AND THE ARGUMENT IS `captureMedia()`'s ONE STEP
     * FURTHER ON.** That one is a tenant lookup, a classification read, an insert
     * and a queue dispatch; this is a queue dispatch whose job **sends a text
     * message to a member of the public and debits two ledgers**. Anything on
     * this path placed before `suppressFromCarrier()` would sit between somebody
     * saying STOP and the refusal being written, which `stop()`'s docblock
     * forbids by name — and this is the step where that would cost the most.
     *
     * ⛔ **AND UNLIKE THE OTHER TWO IT DOES NOT RUN FOR EVERY KEYWORD.** `$filed`
     * is null for STOP, START and HELP, because {@see InboundThreading} refuses to
     * thread a compliance instruction — *"putting them in a conversation would
     * invite a business owner to answer a withdrawal of consent conversationally,
     * which is the one reply that must never be sent"*. The null check below is
     * therefore that rule being honoured rather than a defensive guard, and it is
     * also how a shared-pool-number text (no tenant) and a text from a stranger
     * (no contact) reach nothing: all three are the same `null`.
     *
     * ⚠️ **THE TENANT HAS TO BE RE-ENTERED, BECAUSE THIS PATH HAS NONE.** The
     * class docblock's first rule: an inbound STOP has no tenant, and everything
     * here follows from it. `InboundThreading` resolved one from *our* number
     * (dedicated number lookup) and returned a thread carrying it; acting as that business is reading
     * the thread's own answer rather than inferring a second one.
     *
     * ⚠️ **AND IT SWALLOWS ITS OWN FAILURE**, for `linkToCampaign()`'s reason
     * exactly. An exception escaping here turns this webhook's 200 into a 500,
     * Infobip answers a 500 by redelivering, and the `inbound_messages` unique
     * index refuses the replay — so the retry storm buys nothing and costs every
     * other message in the same delivery batch. **A message the assistant could
     * not be asked to answer is still a received message**: it is threaded, it is
     * on the Inbox, and a person can answer it.
     *
     * @param  ?FiledInboundMessage  $filed  The thread, the `messages` row id and
     *                                       the body, or null when the message
     *                                       threaded nowhere.
     * @param  bool  $hasInboundMedia  Skill 12's trigger, carried so
     *                                 `AgentSkills::forThread()` can light it.
     *                                 ⚠️ **Whether media *arrived*, not whether it
     *                                 was kept** — a covered entity's picture is
     *                                 refused by 4166 and the customer still said
     *                                 *"here's a photo"*, which the assistant has
     *                                 to be able to acknowledge.
     */
    private function answerWithAssistant(
        ?FiledInboundMessage $filed,
        string $providerMessageId,
        bool $hasInboundMedia,
    ): void {
        if (! $filed instanceof FiledInboundMessage) {
            return;
        }

        try {
            Tenancy::actingAs((int) $filed->thread->business_id, function () use (
                $filed,
                $providerMessageId,
                $hasInboundMedia,
            ): void {
                $this->agent->answer(
                    message: $filed,
                    // ⚠️ **THE CARRIER'S OWN HANDLE IS THE OCCASION, AND IT IS
                    // REQUIRED TO BE STABLE ACROSS REDELIVERIES.**
                    // `AnswerAgentTurnJob::idempotencyKey()` records the defect
                    // that made it a parameter: keyed on the turn count instead,
                    // a queue redelivery recomputed a different key and **sent a
                    // second message to the customer**. A vendor message id is
                    // the same string on every attempt; anything derived here is
                    // not.
                    occasion: $providerMessageId,
                    hasInboundMedia: $hasInboundMedia,
                );
            });
        } catch (Throwable $e) {
            // ⚠️ **THE CLASS NAME AND OUR OWN ROW ID, NEVER THE SENDER AND NEVER
            // THE WORDS.** The two things this path holds are a member of the
            // public's mobile and the message they wrote; a turn that could not
            // be dispatched must not pay for itself with either.
            Log::warning('An inbound message was threaded but the assistant could not be asked to answer it.', [
                'reason' => $e::class,
                'provider_message_id' => $providerMessageId,
                'actor' => self::ACTOR,
            ]);
        }
    }

    /**
     * Keep what the account holder said, and tell them it arrived — wave 39
     * lane C, row 10720+.
     *
     * ⛔ **NEVER `inbound_messages`, ON THAT TABLE'S OWN REFUSAL.** See
     * `App\Models\OwnerReply`'s creating migration for the full argument: the
     * refusal to store a body is scoped to a subject with no tenant, and by
     * this point the reply has resolved to exactly one business.
     *
     * ⛔ **AN OWNER WHO HAS SAID STOP IS NOT STORED — WAVE 40 LANE A (10830).**
     * Until this check existed, `businessesFor()` matched on `e164` alone and
     * never asked `stopped_at`, so an account holder who had opted out of the
     * owner channel went on having their words written here, an activity item
     * filed and an audit row recorded — **and was still not threaded to the
     * Inbox and still not answered by the assistant.** They were in a channel
     * that stored what they typed and answered nothing. ⚠️ **The fix is on this
     * arm rather than in `businessesFor()`**: that lookup is run once and used
     * by STOP, START and this branch, and filtering stopped rows out of it
     * would leave an owner who said STOP unable ever to say START.
     * ⚠️ **WHAT HAPPENS INSTEAD IS NOTHING, AND THE ALTERNATIVE WAS WORSE.**
     * Falling through to {@see InboundThreading} would hand the account
     * holder's own message to the AI assistant as though they were a stranger,
     * which is the exact misrouting the owner arm exists to stop. `CLAUDE.md`'s
     * tiebreakers point the same way — less stored PII, less support surface —
     * and **whether a stopped owner's text should be answered at all is a
     * ruling nobody has made** (10830).
     *
     * ⚠️ **AN EMPTY BODY WRITES NOTHING**, because `owner_replies.body` is
     * `NOT NULL` and there is nothing honest to put there — Infobip's own
     * inbound model allows a message with no text, and a placeholder string
     * would read as something the owner said rather than as the carrier
     * omitting the field.
     *
     * ⚠️ **IT SWALLOWS ITS OWN FAILURE, FOR `linkToCampaign()`'s REASON.** An
     * exception escaping here turns this webhook's 200 into a 500, and Infobip
     * answers a 500 by redelivering — against a suppression path this branch
     * never touches and an `inbound_messages` row that will refuse the replay,
     * so the retry storm buys nothing. **A reply we could not store is still a
     * reply we received**; the owner not seeing a confirmation is the honest
     * failure, never a second HELP reply or a doubled record.
     *
     * ✅ **AND SINCE WAVE 40 LANE A THE ROW SAYS WHAT IT APPEARS TO ANSWER**
     * (10829). `owner_replies.in_reply_to_notification_id` is filled from
     * {@see OwnerNotifications::latestFor()} at the one moment both halves are
     * in hand — `linkToCampaign()`'s own rule (4236), applied to the owner
     * channel. ⛔ **It is an inference and not a fact about what the owner
     * meant**: no owner-directed message carries a reply token or a numbered
     * option, so recency is the only signal there is, and null — *"we do not
     * know what this answers"* — is the common case rather than the exception.
     * ⛔ **AND NOTHING IN THIS PLATFORM STILL ACTS ON THE WORDS.** 10722 is
     * unchanged: no automation resumes, no campaign is triggered, no
     * `AgentTurns` call is made, and no numbered-reply command grammar exists
     * (10832 refuses one and says what has to exist first). What this closes is
     * *"a reply had nothing it could be an answer to"*, never *"something acts
     * on it"*. ⚠️ **THIS READ *"nothing still READS the words"* UNTIL WAVE 41
     * LANE E** (11110, 11111): `App\Livewire\Admin\OwnerChannelTexts` reads
     * them, behind the admin gate and a second factor, with the read audited in
     * the tenant's own log. **A person looking is not the platform acting**, and
     * `tests/Feature/Architecture/OwnerChannelTest.php` is where that
     * distinction fails a build.
     *
     * @return null Always. `$filed` in {@see self::handle()} stays null on
     *              this arm precisely as it does for STOP, START and HELP —
     *              this is a side effect, never a conversation to answer.
     */
    private function recordOwnerReply(
        int $businessId,
        string $providerMessageId,
        ?string $text,
        ?string $receivedAt,
    ): null {
        $body = trim((string) $text);

        if ($body === '') {
            return null;
        }

        if ($this->owner->hasSaidStop($businessId)) {
            return null;
        }

        try {
            Tenancy::actingAs($businessId, function () use ($businessId, $body, $providerMessageId, $receivedAt): void {
                OwnerReply::query()->create([
                    'provider_message_id' => $providerMessageId,
                    'body' => $body,
                    'in_reply_to_notification_id' => $this->notifications->latestFor($businessId)?->getKey(),
                    'received_at' => $receivedAt,
                    'created_at' => now(),
                ]);

                $item = $this->activity->record(AutopilotActionType::OwnerReplyReceived);

                $this->audit->record('owner_channel.reply_received', self::ACTOR, $item);
            });
        } catch (Throwable $e) {
            // ⚠️ **THE CLASS NAME AND OUR OWN IDS, NEVER THE WORDS.** The one
            // thing this path holds that must never reach a log file is what
            // the account holder actually typed.
            Log::warning('An owner reply was identified but could not be stored.', [
                'reason' => $e::class,
                'business_id' => $businessId,
                'provider_message_id' => $providerMessageId,
                'actor' => self::ACTOR,
            ]);
        }

        return null;
    }

    /**
     * What this inbound message cost **us**, on the internal book — 2549's owed
     * writer, closed at decision 4804.
     *
     * ⛔ **`MessageCostKind::InboundSms` AND `InboundMms` HAD NO WRITER FROM THE
     * DAY THE ENUM SHIPPED** (2555, 3728, 4689(a)), which is `CLAUDE.md`'s
     * most-repeated failure — 272's shape — and its tell exactly: *"an isolation
     * test passes perfectly against a table nothing writes."* Both cases were
     * argued, seeded, priced and read by {@see MessageRates::pricedKinds()}, and
     * nothing anywhere in `app/` ever booked one, so the margin book omitted an
     * entire direction of traffic while every test of it passed.
     *
     * ⚠️ **THIS IS THE MARGIN BOOK AND NOT THE CEILING, AND THE TWO MUST NOT BE
     * CONFLATED** (4689(a)). An inbound message **debits no credit and must
     * not** — `SendCredits` says so in terms (named in prose, never with a
     * `{@see}`: Pint's `fully_qualified_strict_types` promotes one into a real
     * `use`, and it did exactly that here on this slice's first `composer lint`,
     * leaving this file importing a credit service it must never call) — and the
     * enum's own docblock gives the reason: *"charging a tenant a credit because
     * their customer replied is the wrong product, and it would also be a charge
     * driven by somebody else's behaviour."* Nothing here bounds anything; it
     * records what we were billed.
     *
     * ⛔ **AND THAT IS PRECISELY WHY IT IS WORTH BOOKING.** The AI two-way
     * conversation makes inbound volume unbounded above — *"a conversation has
     * as many as the customer wants to send, and every one is a cost with no
     * retail credit behind it"* — so this is the one direction of traffic whose
     * cost has no retail line beside it to notice it.
     *
     * ## The tenant, and the gap that comes with it
     *
     * ⚠️ **THE SAME LOOKUP {@see self::countAgainstSender()} MAKES, FOR THE SAME
     * REASON, AND WITH THE SAME HOLE.** The tenant is {@see TenantNumbers::tenantFor()}
     * on **our** receiving number — dedicated number reverse lookup — never an inference from
     * the sender's history, which 2125 refuses because it is wrong the first time
     * somebody is a customer of two tenants. ⛔ **So an inbound message on the
     * shared Lane A pool number is booked nowhere**: `tenantFor()` answers null,
     * `MessageCostLedger::record()` opens with `Tenancy::idOrFail()` and there is
     * no tenant to charge it to. **The margin book therefore under-reports
     * inbound cost by exactly the traffic on the shared pool number**, which is
     * the same shape and the same population as the complaint counter's
     * under-reporting.
     *
     * ✅ **AND IT IS NO LONGER ONLY WRITTEN DOWN — THE SIZE OF IT IS NOW
     * ASKABLE** (4925). `MessageCostLedger::unattributedInboundCount()` counts
     * exactly the messages this early return skips, so a flat inbound book can
     * be told apart from quiet customers. ⛔ **It is a count and not a cost**,
     * because pricing these would need a rate nobody has entered, and it changes
     * nothing here: the row still is not written, because there is no tenant to
     * write it against.
     *
     * ⚠️ **PLATFORM-SCOPED WOULD HAVE BEEN THE TIDY WRONG ANSWER.**
     * `message_cost_entries` is tenant-owned and RLS-`FORCE`d; a row with no
     * business would need a nullable tenant on a table whose whole read path
     * (`totalCost()`) is per-tenant, and the figure it produced would belong to
     * nobody.
     *
     * ## Where it sits, and what it may not do
     *
     * ⚠️ **AFTER THE SUPPRESSION, THE LINKAGE AND THE MEDIA, AND BEFORE THE
     * ASSISTANT.** This class orders its steps by what must survive: everything
     * that must land whatever happens lands first, and the step that *texts a
     * member of the public and spends money* is last. This one writes a single
     * row, opens no vendor connection and dispatches nothing, so it belongs with
     * the other row-writers and ahead of the dispatch.
     *
     * ⚠️ **IT SWALLOWS ITS OWN FAILURE**, for {@see self::linkToCampaign()}'s
     * reason and for 2547's: an exception escaping here turns this webhook's 200
     * into a 500, Infobip answers a 500 by redelivering, and the
     * `inbound_messages` unique index refuses the replay — so the retry storm
     * buys nothing and costs every other message in the same delivery batch. **A
     * bookkeeping gap must never stop a message**, and on the inbound side it
     * must never cost us the suppression either.
     *
     * ⚠️ **THE KIND IS WHETHER MEDIA *ARRIVED*, NEVER WHETHER IT WAS KEPT.**
     * {@see self::captureMedia()} refuses a covered entity's inbound picture
     * (4166) and **the carrier billed us for the MMS regardless** — booking that
     * one as an SMS would understate a real charge because of a decision made on
     * our side of the wire. It is the same distinction `answerWithAssistant()`
     * draws about `$hasInboundMedia` one method down, for a different reason.
     *
     * ## The segment count, which is the carrier's and never ours — 4811, 4923
     *
     * ⛔ **`SmsSegments` IS NOT USED HERE AND MUST NOT BE, AND THIS IS A
     * DELIBERATE ANSWER RATHER THAN AN OMISSION.** 4800 made that class the one
     * segment estimator in `app/` and 4801 lints it singular, so the obvious
     * reading of 4811 is *"call it on the inbound body too"*. **That would be
     * wrong twice.**
     *
     * ⚠️ **FIRST, THE CARRIER ALREADY TELLS US.** Infobip's inbound SMS webhook
     * carries `smsCount` on every result — *"The number of parts the message
     * content was split into"* — verified against the raw artefact rather than
     * remembered:
     * `https://www.infobip.com/docs/api/channels/sms/inbound-sms/receive-inbound-sms-messages.md`,
     * read 2026-08-18, the `SmsMoReport` schema. That is the same standing this
     * codebase already gives the delivery receipt's own count (2549: *"the
     * figure a reconciliation should eventually trust over this one"*), and it
     * arrives **with** the message rather than having to be predicted before it.
     *
     * ⚠️ **SECOND, THE ESTIMATE WOULD BE WORSE HERE THAN IT IS OUTBOUND, NOT
     * MERELY REDUNDANT.** `SmsSegments` counts characters at 160/153 and models
     * neither GSM-03.38's alphabet nor the UCS-2 fallback — its own docblock
     * says so. Outbound that is tolerable because **we** wrote the body and our
     * composers are plain ASCII. Inbound is a stranger typing on a handset, and
     * a single emoji drops the real budget to 70/67, so the estimate
     * under-counts by more than half on exactly the messages most likely to be
     * multi-part. **Applying it to a body Infobip has already reassembled is
     * re-deriving, badly, a fact the vendor stated.** So the class stays
     * outbound-only and stays in `App\Services\Messaging\Outbound` — the
     * namespace is right rather than merely inherited.
     *
     * ⚠️ **THE COUNT IS RECORDED WHATEVER THE SCHEDULE DOES WITH IT.**
     * `message_cost_entries.segments` is nullable so *"this product is not
     * billed by segment"* is sayable, and null is what an unpriced-by-segment
     * inbound message used to store — which threw the count away and made 4811's
     * shortfall **unmeasurable**. It is now stored, so the gap between what was
     * booked and what a per-part contract would have charged is a query rather
     * than an unknown.
     *
     * ⛔ **WHETHER IT IS *MULTIPLIED* IS 4805's QUESTION AND IS ANSWERED THE SAME
     * WAY: BY AN OPERATOR, NOT BY US.** The vendor documents the count and the
     * per-message price on the same schema and **does not state the billing
     * rule**, so *"price per one SMS × the number of parts"* is a plausible
     * reading and a plausible reading is precisely what 255, 277, 684 and 1349
     * were. `messaging.carrier_bills_inbound_sms_per_segment` seeds **false**,
     * which books per message — **short rather than wrong**, 4805's own
     * distinction — and the recorded count is what makes flipping it a
     * recomputation instead of a guess.
     *
     * ⚠️ **MMS CARRIES NO COUNT AND IS NOT GIVEN ONE.** `MO_MMS_2` is a
     * different renderer with no `smsCount` at all (4259), and MMS is billed per
     * message plus media on the outbound side too. It books null, which is the
     * true statement.
     *
     * @param  bool  $hasMedia  Whether this arrived as an MMS.
     * @param  ?int  $segments  The carrier's own `smsCount`, or null when the
     *                          payload omitted it. ⚠️ **Null is "the carrier did
     *                          not say", never "one part"** — defaulting it to 1
     *                          would record a claim Infobip never made, on the
     *                          book whose whole job is reconciliation.
     */
    private function recordCost(
        InboundMessage $record,
        string $providerMessageId,
        ?string $toNumber,
        bool $hasMedia,
        ?int $segments = null,
    ): void {
        if ($toNumber === null) {
            // Some inbound payloads omit the receiving number. There is nothing
            // to look a tenant up by, and the alternatives are the inference the
            // class docblock refuses. `countAgainstSender()` returns here too.
            return;
        }

        $businessId = $this->numbers->tenantFor($toNumber);

        if ($businessId === null) {
            // The shared Lane A pool number, or a number no longer ours. See
            // this method's docblock: the cost is real and is booked nowhere.
            return;
        }

        $kind = $hasMedia ? MessageCostKind::InboundMms : MessageCostKind::InboundSms;

        // ⚠️ **THE COUNT BELONGS TO THE SMS RENDERER ONLY, AND A ZERO OR A
        // NEGATIVE IS DISCARDED RATHER THAN CLAMPED.** `smsCount` is optional in
        // Infobip's own schema, so a missing, malformed or nonsensical value is
        // an ordinary state — and `max(1, …)` would turn "the carrier did not
        // say" into "the carrier said one", which is the false-precision the
        // nullable column exists to avoid.
        $parts = $kind === MessageCostKind::InboundSms && $segments !== null && $segments >= 1
            ? $segments
            : null;

        try {
            // ⚠️ **NULL MEANS NO RATE IS SET, WHICH IS NOT THE SAME AS FREE**
            // (2547). Every rate in this schedule seeds to zero and zero means
            // unset — Infobip prices per account and the figure is the owner's
            // to enter. Until one is entered this writes nothing, and nothing
            // else about the inbound path changes.
            $costMillicents = $this->rates->costFor($kind, $parts);

            if ($costMillicents === null) {
                return;
            }

            Tenancy::actingAs($businessId, function () use ($kind, $costMillicents, $providerMessageId, $record, $parts): void {
                $this->costs->record(
                    kind: $kind,
                    costMillicents: $costMillicents,
                    // ⚠️ **THE CARRIER'S OWN `smsCount`, RECORDED WHETHER OR NOT
                    // THE SCHEDULE PRICED BY IT** (4923). Storing it is what
                    // turns 4811's shortfall from an unknown into a query.
                    segments: $parts,
                    // ⚠️ **THE CARRIER'S OWN HANDLE, WHICH IS THE SAME STRING ON
                    // EVERY REDELIVERY.** Anything derived here — a timestamp, a
                    // counter — would differ per attempt and book the same
                    // carrier charge twice, in a table nobody reconciles against
                    // a statement. The `inbound_messages` unique index already
                    // refuses a replay before this line is reached; this is the
                    // second lock, because the row insert and this write are not
                    // one transaction.
                    idempotencyKey: 'inbound:'.$providerMessageId.':'.$kind->value,
                    refType: InboundMessage::COST_REFERENCE_TYPE,
                    refId: (int) $record->getKey(),
                );
            });
        } catch (Throwable $e) {
            // ⚠️ **THE CLASS NAME AND OUR OWN IDS, NEVER THE SENDER.** The one
            // identifier this path holds is a member of the public's mobile
            // number, and a bookkeeping write that failed must not pay for
            // itself with the thing every other rule on this path protects.
            //
            // ⚠️ **AND THE RECONCILIATION QUERY IS IN `MessageCostLedger`'s
            // DOCBLOCK RATHER THAN LEFT TO BE DERIVED** (3898). No counter and
            // no metric: a durable counter with one writer and no reader is the
            // very shape this method exists to close.
            Log::warning('An inbound message was received and its carrier cost was not booked.', [
                'reason' => $e::class,
                'business_id' => $businessId,
                'inbound_message_id' => $record->getKey(),
                'actor' => self::ACTOR,
            ]);
        }
    }

    /**
     * Keep the pictures on this message, if there are any — T176 P10, skill 12.
     *
     * ⛔ **LAST, AFTER EVEN THE CAMPAIGN LINKAGE, AND FOR THE SAME ARGUMENT ONE
     * STEP FURTHER ON.** {@see self::linkToCampaign()} is a tenant lookup, a hash
     * scan and an insert; this is a tenant lookup, a classification read, an
     * insert **and a queue dispatch**. Every one of them placed before
     * `suppressFromCarrier()` would sit between somebody saying STOP and the
     * refusal being written, which `stop()`'s docblock forbids by name — and a
     * carrier tests HELP and does not test this.
     *
     * ⚠️ **IT RUNS FOR EVERY KEYWORD, INCLUDING STOP.** A photograph attached to
     * the word STOP is still a photograph that person sent this business, and
     * the suppression is written and platform-wide either way.
     *
     * ⚠️ **AND IT SWALLOWS ITS OWN FAILURE**, for `linkToCampaign()`'s reason.
     * An exception escaping here turns this webhook's 200 into a 500, and
     * Infobip answers a 500 by redelivering — against a suppression already
     * written and an `inbound_messages` row that will refuse the replay, so the
     * retry storm buys nothing and costs every other message in the same
     * delivery batch. **An MMS whose picture we could not keep is still a
     * received message**: it reaches the owner, it simply opens without a
     * picture.
     *
     * ⛔ **AND SINCE WAVE 41 LANE E IT DOES NOT RUN FOR A STOPPED ACCOUNT
     * HOLDER** (11100). {@see self::recordOwnerReply()} has refused to store a
     * stopped owner's **words** since 10830 and this path went on keeping their
     * **picture** — the same message, two opposite answers, and the flattering
     * one on the attachment. ⚠️ **The refusal is decided in
     * {@see InboundMediaCapture}, not here**, because the receiving number's
     * tenant is resolved there and this arm must not refuse an account holder
     * of business A a photograph they sent to business B as B's customer. What
     * this method contributes is the one fact that class cannot see: which of
     * the sender's businesses have stopped.
     *
     * @param  list<InboundMediaPart>  $media
     * @param  list<int>  $ownerBusinessIds  Every business that has registered
     *                                       this sender as its account holder — the list
     *                                       {@see self::handle()} already resolved once.
     */
    private function captureMedia(InboundMessage $record, array $media, array $ownerBusinessIds): void
    {
        if ($media === []) {
            return;
        }

        try {
            $this->media->capture($record, $media, $this->stoppedAmong($ownerBusinessIds));
        } catch (Throwable $e) {
            // ⚠️ **THE CLASS NAME AND OUR OWN ROW ID, NEVER THE SENDER AND NEVER
            // THE URL.** The two identifiers this path holds are a member of the
            // public's mobile number and an address a stranger chose; a capture
            // that failed must not pay for itself with either.
            Log::warning('An inbound message carried media that could not be captured.', [
                'reason' => $e::class,
                'inbound_message_id' => $record->getKey(),
                'actor' => self::ACTOR,
            ]);
        }
    }

    /**
     * Which of this sender's own businesses have asked us to stop — wave 41
     * lane E, decision 11100.
     *
     * ⚠️ **ASKED ONLY ON AN MMS**, because {@see self::captureMedia()} returns
     * before this on the ordinary text path. One query per registered business
     * on a message that already carries pictures is not a cost worth designing
     * around, and reusing {@see self::recordOwnerReply()}'s own call would tie
     * two arms together that run on different keywords — a photograph attached
     * to the word STOP reaches this and never reaches that.
     *
     * ⚠️ **`hasSaidStop()` ANSWERS TRUE FOR A BUSINESS WITH NO REGISTERED
     * NUMBER AT ALL**, which cannot happen here: every id in `$ownerBusinessIds`
     * came from `OwnerConsentService::businessesFor()`, which reads that very
     * table.
     *
     * @param  list<int>  $ownerBusinessIds
     * @return list<int>
     */
    private function stoppedAmong(array $ownerBusinessIds): array
    {
        return array_values(array_filter(
            $ownerBusinessIds,
            fn (int $businessId): bool => $this->owner->hasSaidStop($businessId),
        ));
    }

    /**
     * File this reply against the send it answers — T176 R20, patch P20.
     *
     * ⛔ **LAST, AND THE ORDER IS THE SAME ARGUMENT `stop()` MAKES ABOUT ITS OWN
     * THREE STEPS.** Compliance is a person's instruction and this is context: a
     * tenant lookup, a hash scan and an insert placed before
     * `suppressFromCarrier()` would sit between somebody saying STOP and the
     * refusal being written, which that method's docblock forbids by name. It is
     * also after the HELP reply, because a carrier tests HELP and does not test
     * this.
     *
     * ⚠️ **IT RUNS FOR EVERY KEYWORD INCLUDING STOP, WHICH IS NOT AN
     * OVERSIGHT.** A STOP is the strongest reply a campaign can draw, and the
     * question asked after a complaint is *which message provoked it*. Skipping
     * the linkage on the keyword branches would lose exactly the replies worth
     * having — and it changes nothing about the suppression, which is already
     * written and is platform-wide either way.
     *
     * ⚠️ **AND IT SWALLOWS ITS OWN FAILURE**, for `countAgainstSender()`'s
     * reason. An exception escaping here turns this webhook's 200 into a 500,
     * and Infobip answers a 500 by redelivering — against a suppression already
     * written and an `inbound_messages` row that will refuse the replay, so the
     * retry storm buys nothing and costs every other message in the same
     * delivery batch. **An unlinked reply is still a reply**: it reaches the
     * owner, it simply opens without campaign context.
     *
     * ⚠️ **THE THREAD IS A PARAMETER AND HAS NO DEFAULT** (4236). `null` here is
     * a real and common answer — a STOP threads nowhere and is linked anyway —
     * so a defaulted argument would let a future caller drop the join key with
     * no diagnostic, and the symptom would be exactly the one this lane exists
     * to close: a linkage nothing can read back.
     *
     * @param  ?Conversation  $thread  The thread this message was filed on, or
     *                                 null when it threaded nowhere.
     */
    private function linkToCampaign(InboundMessage $record, ?Conversation $thread): void
    {
        try {
            $this->campaigns->link($record, $thread);
        } catch (Throwable $e) {
            // ⚠️ **THE CLASS NAME AND OUR OWN ROW ID, NEVER THE SENDER.** The
            // one identifier this path holds is a member of the public's mobile
            // number, and a convenience that failed must not pay for itself with
            // the thing every other rule on this path protects.
            Log::warning('An inbound message was received but could not be linked to the send it answers.', [
                'reason' => $e::class,
                'inbound_message_id' => $record->getKey(),
                'actor' => self::ACTOR,
            ]);
        }
    }

    /**
     * Write the message, or return null because it is already written.
     *
     * ⚠️ **THE UNIQUE INDEX IS THE DEFENCE AND THE `catch` IS HOW WE READ IT.**
     * A `->exists()` check before the insert is passed by both of two concurrent
     * redeliveries; only the database can refuse the second. `StripeEvent`
     * established the shape, and `SqlState` is where this codebase already keeps
     * the "which violation was that" question so it is not asked with a string
     * comparison against a driver message.
     */
    private function record(
        string $providerMessageId,
        string $hash,
        InboundKeyword $keyword,
        ?string $receivedAt,
        ?string $toNumber,
    ): ?InboundMessage {
        try {
            // ⚠️ **WRAPPED IN A TRANSACTION SO THE VIOLATION ROLLS BACK TO A
            // SAVEPOINT, AND THIS IS A POSTGRES FACT RATHER THAN A STYLE
            // CHOICE.** A failed statement inside an open transaction aborts the
            // whole transaction — every later statement returns `25P02: current
            // transaction is aborted` until it ends. So catching the unique
            // violation is not enough on its own: without the savepoint this
            // method would swallow the duplicate correctly and then poison every
            // query after it, including the ones handling the *other* messages
            // in the same batch. The suite found it on the first run.
            return DB::transaction(fn (): InboundMessage => InboundMessage::query()->create([
                'provider_message_id' => $providerMessageId,
                'identifier_type' => OutreachChannel::Sms,
                'value_hash' => $hash,
                // ⚠️ **OUR NUMBER, IN PLAIN TEXT, BESIDE THE SENDER'S HASH —
                // TWO OPPOSITE RULES ON ONE ROW AND BOTH ARE RIGHT.** The hash
                // above protects a member of the public's mobile number. This is
                // one of ours, the same string `phone_numbers.e164` holds, and
                // hashing it would make the only question it exists to answer —
                // which of our numbers did this arrive on — unanswerable.
                'to_number' => $toNumber,
                'keyword' => $keyword,
                'received_at' => $receivedAt,
                'created_at' => now(),
            ]));
        } catch (QueryException $e) {
            // 23505 is Postgres' unique_violation. Through `SqlState` rather
            // than `$e->getCode()`, because that value is an int on some driver
            // paths and a bare comparison silently misses the match — the
            // failure that class was written to stop happening a third time.
            if (SqlState::of($e) === '23505') {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Honour a STOP.
     *
     * ⚠️ **`SuppressionReason::Stop` IS THE ONE CLASS THAT LIFTS, AND THAT IS
     * REQUIRED RATHER THAN CONVENIENT.** Carrier rules oblige us to honour a
     * later START, so classifying a carrier STOP as anything stricter — a
     * complaint, say — would make it permanently unliftable and leave us unable
     * to comply with the other half of the same rulebook.
     *
     * ⚠️ **THE SUPPRESSION IS WRITTEN FIRST AND THE COUNTER SECOND, AND THE
     * ORDER IS NOT COSMETIC.** A counter is a containment; a suppression is a
     * person's instruction. If the two ever cannot both be written, the one that
     * must survive is the refusal — so nothing between the caller and
     * `suppressFromCarrier()` may fail, and everything that can fail sits after
     * it and swallows its own exception.
     *
     * ⛔ **AND THE CONFIRMATION IS THIRD, WHICH IS A THIRD ORDERING DECISION AND
     * NOT A LIST THAT GREW** (3267). It is last of the three because it is the
     * only one that crosses a vendor boundary: `services.infobip.timeout`
     * defaults to ten seconds, and the complaint counter above feeds 2102's
     * automatic trip, which must not wait on a carrier round trip to be
     * recorded. It is after the suppression for the harder reason —
     * {@see ComplianceReplies::answerStop()}'s own docblock: a confirmation sent
     * before the refusal is written is a promise made before the thing it
     * promises exists.
     *
     * ⚠️ **IT SWALLOWS ITS OWN FAILURE TOO**: `answerStop()` returns a bool and
     * catches everything, for the retry-storm reason its own docblock gives. The
     * return value is discarded because there is nothing this class could do
     * with it — a confirmation that could not be delivered has already been
     * logged, and the refusal it confirms is written either way.
     *
     * ⛔ **THAT SENTENCE SAID "BY CONSTRUCTION" AND WAS FALSE FOR ONE COMMIT**
     * (3285). Three database calls — two registry reads and
     * `TenantNumbers::tenantFor()` — sat *outside* that method's `try`, so a
     * connection blip threw straight out through `handle()` into the carrier
     * webhook: a 500 that Infobip answers by redelivering, and, worse, **the
     * loss of every later message in the same delivery batch**, against this
     * controller's own rule that one unreadable entry must not cost the STOP
     * beside it. The `try` was widened, so the claim is true now — mechanism
     * first, sentence second, which is the order 314–316 asks for.
     *
     * @param  list<int>  $ownerBusinessIds  Every business this sender is a
     *                                       registered owner-notify number
     *                                       for (10540 phase 3) — every one
     *                                       is stopped, because an ambiguous
     *                                       STOP should widen suppression
     *                                       rather than narrow it.
     */
    private function stop(string $from, ?string $toNumber, array $ownerBusinessIds): void
    {
        $this->consent->suppressFromCarrier(
            identifier: $from,
            channel: OutreachChannel::Sms,
            class: SuppressionReason::Stop,
            actor: self::ACTOR,
        );

        foreach ($ownerBusinessIds as $businessId) {
            $this->owner->stop($businessId);
        }

        $this->countAgainstSender($toNumber);

        $this->replies->answerStop($from, $toNumber);
    }

    /**
     * Charge one STOP to the tenant whose number it arrived on.
     *
     * ⚠️ **BOTH COUNTERS MOVE, AND THEY ARE NOT THE SAME FACT** (2499 names
     * both). `opted_out` is every withdrawal of consent by any route;
     * `complaints` is the narrower proxy {@see SendingHealth} describes — *"a
     * STOP that arrived in reply to a message we sent"* — and it is the figure
     * `SendingGuard::shouldTrip()` divides by `delivered`. A carrier STOP is
     * both at once. ⚠️ **Today it is the ONLY writer of either**, so the two
     * columns will read identically until the web and email unsubscribe paths
     * call `recordOptOut()`; that is a known incompleteness rather than a claim
     * that every opt-out is a complaint, and `optOutRateBp()` has no reader in
     * `app/` in the meantime.
     *
     * ⚠️ **IT SWALLOWS ITS OWN FAILURE, FOR {@see ComplianceReplies}' REASON.**
     * This runs inside a carrier webhook: an exception escaping here turns a 200
     * into a 500, and Infobip answers a 500 by redelivering the same inbound
     * message — against a suppression that is already written and an
     * `inbound_messages` row that will refuse the replay, so the retry storm
     * buys nothing. **The refusal has already landed by the time anything below
     * can throw**, which is what makes swallowing affordable here and would not
     * make it affordable one line earlier.
     */
    private function countAgainstSender(?string $toNumber): void
    {
        if ($toNumber === null) {
            // Some inbound payloads omit the receiving number. There is nothing
            // to look a tenant up by, and the alternatives — the sender's
            // history, the platform's own aggregate — are the inference the
            // class docblock refuses.
            return;
        }

        $businessId = $this->numbers->tenantFor($toNumber);

        if ($businessId === null) {
            // The shared Lane A pool number, or a number no longer ours. See the
            // class docblock: the refusal still stands platform-wide and the
            // complaint is counted nowhere, which under-reports the rate.
            return;
        }

        try {
            Tenancy::actingAs($businessId, function (): void {
                $this->health->recordOptOut(OutreachChannel::Sms);
                $this->health->recordComplaint(OutreachChannel::Sms);
            });
        } catch (Throwable $e) {
            // ⚠️ **THE CLASS NAME, NEVER THE NUMBER.** The one identifier this
            // path holds is a member of the public's mobile, and a containment
            // that failed to count must not pay for itself with the thing it
            // exists to protect.
            Log::warning('A STOP was honoured but could not be counted against the tenant it arrived for.', [
                'reason' => $e::class,
                'business_id' => $businessId,
                'actor' => self::ACTOR,
            ]);
        }
    }

    /**
     * Honour a START.
     *
     * ⚠️ **THIS LIFTS THROUGH `suppression_lifts` AND DELETES NOTHING**, which
     * §2.10.4 names as its own test. `ConsentService::lift()` writes a reversal
     * row matched on the refusal's generation, so STOP → START → STOP is three
     * permanent facts. Deleting the opt-out instead would make the platform
     * forget it was ever told to stop, and the second STOP would be born with no
     * history behind it.
     *
     * The return value is deliberately discarded: `lift()` answers false when
     * there was nothing to release, and somebody texting START having never
     * opted out is an ordinary message rather than a failure.
     *
     * @param  list<int>  $ownerBusinessIds  See {@see self::stop()} — every
     *                                       match is lifted, the mirror of
     *                                       every match being stopped.
     */
    private function start(string $from, array $ownerBusinessIds): void
    {
        $this->consent->liftFromCarrier(
            identifier: $from,
            channel: OutreachChannel::Sms,
            source: LiftSource::CarrierStart,
            actor: self::ACTOR,
        );

        foreach ($ownerBusinessIds as $businessId) {
            $this->owner->start($businessId);
        }
    }
}
