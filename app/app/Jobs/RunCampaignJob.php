<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MessageSender;
use App\Enums\AutopilotActionType;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\SendRefusalReason;
use App\Exceptions\MessageCannotBeComposed;
use App\Exceptions\TextNotDeliverable;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\Location;
use App\Services\ActivityService;
use App\Services\Campaigns\BroadcastPreconditions;
use App\Services\Campaigns\CampaignMedia;
use App\Services\Campaigns\SendCollisionArbiter;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SendPermit;
use App\Services\Messaging\Composer\ReactComposer;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendKey;
use App\Services\Messaging\Outbound\SendOutcome;
use App\Services\Messaging\SendingGuard;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * One pass of a reactivation campaign (T137 `SL-2`, `REACT-1`).
 *
 * ## Why a pass rather than a job per recipient
 *
 * A pass sends at most `campaigns.batch_size` recipients and re-queues itself
 * for the next, which keeps three things true at once: a stuck pass is short
 * enough to notice, the campaign's own state moves in one place, and — the
 * load-bearing one — **the stop conditions are re-read between every single
 * recipient** rather than once per job.
 *
 * ## The mid-flight trip, which is the whole point of decision 2113
 *
 * ⛔ **`AutopilotJob` CHECKS THE PAUSE, THE SUSPENSION AND THE KILL SWITCH ONCE,
 * AT THE TOP, AND THAT IS NOT ENOUGH HERE.** Every other automation in this
 * codebase does one thing and finishes; a campaign runs for as long as it takes
 * to reach thousands of people, so the gap between "we checked" and "we sent"
 * is the whole run. 2102's failure mode is stated exactly: *"a campaign running
 * overnight while the queue nobody is watching fills"*.
 *
 * 2113 sharpens it. While 10DLC registration was in doubt the complaint-rate
 * exposure was theoretical; it is not, because these sends genuinely go out over
 * the **GOAIEZ** brand from **our own** pool, so the platform carries the
 * carrier reputation for every tenant at once. **The automatic trip on both kill
 * switches moves from a mitigation to a precondition of sending at all.**
 *
 * So {@see self::stopCondition()} is asked **before every recipient**, and it
 * reads five things:
 *
 *   the channel switch     `sms.enabled` in the registry, moved in Ops without a
 *                          deploy. One channel's switch, and the one
 *                          {@see self::canExecute()} already asked once.
 *   the tenant suspension  `TenantSuspension`, ours, for cause.
 *   the tenant pause       `TenantPause`, the owner's or support's on their
 *                          behalf.
 *   {@see BroadcastPreconditions}
 *                          decision 3310's three, and **only for a
 *                          `CampaignKind::Broadcast`** — the tenant's own
 *                          approved 10DLC filing, their own sendable number, and
 *                          a purchased balance. Null for a reactivation.
 *   {@see SendingGuard}    the containment 2101 and 2102 make load-bearing —
 *                          `messaging.global_halt`, this tenant's `SendingPause`,
 *                          and **the automatic complaint-rate trip**.
 *
 * ⛔ **THE FOURTH IS NEW, AND IT IS THE OTHER SIDE OF 2101 ARRIVING FROM THE
 * OWNER — 3310.** 2101
 * accepted, with the cost named, that a reactivation puts a tenant's attested
 * list on **our** brand and **our** number pool, so the complaint rate lands on
 * the platform rather than on the attester. 3310 refuses that trade for a
 * broadcast: it must ride the tenant's own brand and their own number, which
 * moves the reputational exposure onto the party who attested. **It is a
 * containment in the same family as the guard below it**, which is why it lives
 * inside the loop rather than at confirmation.
 *
 * ⛔ **THE FIFTH — THE GUARD — WAS MISSING FROM THE SNAPSHOT THIS FILE WAS
 * RESUMED FROM, AND THE PROSE ABOVE IT ASSERTED IT WAS PRESENT.** ⚠️ *It was
 * the fourth when this paragraph was written; 3310 inserted one above it and
 * renumbered the list rather than leaving a count that no longer resolves.* The
 * paragraph this replaces
 * said *"`L7`'s automatic complaint-rate trip writes `TenantPause`, which is why
 * nothing here needs to know how the trip decided"*. It does not: the merged
 * trip writes a `SendingPause` row through {@see SendingGuard::pause()}, a
 * different table with a different reader, and `TenantPause` has no automatic
 * writer at all. So the runner read a switch the trip never throws, the suite
 * was green, and the one caller the whole containment exists for was the one
 * caller that could not see it — `CLAUDE.md`'s *"protection layer asserted
 * before it is true"*, inside the file whose own docblock quotes 2113.
 *
 * ⛔ **AND IT IS ASKED INSIDE THE LOOP, NEVER BEFORE IT.** A runner that
 * consulted the guard once at enqueue would authorise the whole run on a reading
 * taken before a single message had gone out — therefore before any complaint
 * could exist, because the complaints arrive *because* the campaign is running.
 * Decision 2117 rejected a competing implementation over exactly this.
 *
 * ⚠️ **THE GUARD IS ASKED LAST, AND THAT ORDER IS ITS OWN REASONING BORROWED.**
 * {@see SendingGuard::refusalFor()} can *create* a pause, and it recomputes a
 * rate to decide whether to; asking it after the three cheap durable refusals
 * means a tenant already stopped for another reason does not have their
 * complaint rate recomputed once per skipped recipient for no purpose.
 *
 * ⚠️ **`sms.enabled` AND `messaging.global_halt` ARE TWO SWITCHES AND THAT IS
 * NOW DELIBERATE.** The snapshot refused to invent a second global halt, and it
 * was right to; main has since landed one on purpose, and its own registry
 * description settles the relationship — *"distinct from `sms.enabled`, which is
 * one channel's switch: this one outranks every channel and every tenant"*. The
 * runner reads both because they answer different questions, and it reads the
 * platform one through the guard rather than by name, so there is still exactly
 * one reader of that key on this path.
 *
 * A trip mid-pass leaves the remaining recipients `Skipped` and the campaign
 * un-rewritten, so a resume carries on where it stopped.
 *
 * ⚠️ **`Skipped`, NOT `Refused`, FOR EVERY ONE OF THE FOUR.** A refusal is
 * terminal on a recipient row: it names a `SendRefusalReason` about *that
 * contact* and drops them from the campaign. All four of these are states of the
 * *tenant or the platform* that an operator resumes — `SendingPause` exists to
 * be released by a person ({@see SendingGuard::resume()}) — so marking the
 * audience refused would mean releasing the pause recovered the sending and lost
 * the list.
 *
 * ## What a send with no outcome does, which is the one thing a runner cannot skip
 *
 * ⛔ **A `TextNotDeliverable` USED TO LEAVE THE RECIPIENT ROW UNTOUCHED, AND AN
 * UNTOUCHED ROW IS AN INVITATION TO SEND AGAIN** (7069, closed at 7180).
 * {@see self::record()} was the only writer of a `campaign_recipients` row, so
 * a throw on the wire meant `Pending`, and `Pending` means *not yet attempted*.
 * Every layer that would ordinarily refuse the second attempt was gone: the
 * `SendKey` claim is rolled back with the transaction, this job holds no
 * idempotency claim by design, and `SendCollisionArbiter` reads the two columns
 * the rollback erased. **The retry sent a second marketing text to a member of
 * the public**, and the certain case was not a timeout but
 * `TextNotDeliverable::unreadable('message_id_missing')` — HTTP 200, the
 * destination not refused, the message at Infobip, only its handle missing.
 *
 * So {@see self::answerTransportFailure()} is now the second writer of that
 * row, and it reads the one bit the transport can give it:
 *
 *   **the carrier may hold it**  `CampaignRecipientStatus::Unknown`. Terminal,
 *                                never re-selected, never claimed as sent or as
 *                                refused, counted to the owner at close in its
 *                                own sentence. **The pass stops here** — see
 *                                {@see self::execute()}.
 *   **it provably never left**   `CampaignRecipientStatus::Failed`, which had
 *                                no writer anywhere in `app/` until this. Still
 *                                outstanding, so the contact is attempted
 *                                again, and 2687's fortnight is what stops that
 *                                being for ever.
 *
 * ⚠️ **WHAT THIS COSTS IS A MESSAGE LOST TO A VENDOR BLIP**, and it is the side
 * `AutopilotJob::claimIsSpent()` already chose in writing — *"losing a send is
 * recoverable; sending twice is not"*.
 *
 * ## The credit seam, stated because a merge breaks at seams
 *
 * ⚠️ **THIS JOB DEBITS NOTHING, AND THAT IS AN ASSUMPTION ABOUT `L2`, NOT AN
 * OMISSION.** The send-driver contract's third guarantee is explicit: *"the
 * credit debit is transactional with the send … the ledger is L1's `CreditLedger`
 * and there is exactly one — an implementation of this interface calls it, and
 * never keeps its own count."* A second debit here would charge twice for one
 * message, and a debit here *instead* would not be inside the send's
 * transaction, which is what T137 §3.1 requires. A test pins that this job
 * writes no ledger row, so the day somebody moves the debit it is a deliberate
 * change rather than a silent doubling.
 *
 * ⛔ **THIS SAID "R9'S 'SMS+MMS PAIR = 1 CREDIT' IS TRUE BY CONSTRUCTION HERE",
 * THE OWNER REVERSED THE FIGURE AT 9182, AND HE MOVED IT AGAIN AT 12461** — so
 * **no figure is stated here now** (12487). ⚠️ **What is still true by
 * construction is the half that mattered and it has survived both**: the pair is
 * composed as **one** `OutboundMessage` carrying a body and its media, so it is
 * one `SendKey`, one `send()` and **one movement** — of however many units the
 * rule says, never of one per part. Sending them as two calls and then adding or subtracting a credit would
 * be a second pricing rule living in a runner, and that is still forbidden. ⛔ **The
 * quantity is `SendCredits::creditsFor()`'s, read off the row this send writes;
 * no arithmetic about it lives here.**
 *
 * ## What this job does not decide
 *
 * **Consent, suppression, the registers and the quiet-hours windows are
 * `ConsentService`'s**, asked per recipient at send time with
 * `OutreachPurpose::Marketing` — because reactivation **is** marketing (decision
 * 2100), whatever relationship the tenant attested to. **The composition is
 * `ReactComposer`'s.** **The number, the rate governor and the transport are
 * `L2`'s.**
 *
 * ⛔ **BUT WHAT A REFUSAL *COSTS THE RECIPIENT* IS THIS JOB'S, AND IT GOT IT
 * WRONG FOR EVERY REASON AT ONCE** (2570). `ConsentService` says no; this job
 * decides whether that no is the end of the contact's campaign.
 * {@see self::answerRefusal()} now asks `SendRefusalReason::isTemporary()`
 * instead of terminating every one of them, which is the same distinction the
 * arbiter branch and 2454's four stop conditions were already drawing.
 *
 * ## Nothing dispatched this job, and now something does
 *
 * ⛔ **UNTIL 2578 THE ONLY `dispatch()` IN EXISTENCE WAS THIS CLASS'S OWN
 * RE-QUEUE**, so the campaign engine was unreachable from production: a
 * confirmed, enrolled campaign sat forever and the three constructions in the
 * repository were all tests calling `handle()` by hand. `campaigns:run-due` is
 * the trigger — see `App\Console\Commands\RunDueCampaigns`, which also explains
 * why a sweep rather than a dispatch from `Campaigns::confirm()`.
 */
final class RunCampaignJob extends AutopilotJob
{
    /**
     * How long a pass that reached nobody waits before trying again.
     *
     * See {@see self::closeIfFinished()} for why this is not one minute and not
     * a registry key.
     *
     * ⚠️ **PUBLIC SINCE 2580 BECAUSE THE SWEEP HAS TO OUTLAST IT.**
     * `RunDueCampaigns` treats a campaign as stalled only after twice this long,
     * so that a healthy barren chain is never dispatched a second time — two
     * chains double at every pass. Deriving that window from this constant makes
     * the relationship un-driftable rather than a number somebody has to
     * remember to raise alongside it.
     */
    public const int BARREN_PASS_MINUTES = 15;

    /**
     * How long a recipient may be put off before the campaign gives up on them
     * — decision 2687, closing the ceiling 2589 recorded as owed.
     *
     * ⛔ **2570 TRADED PERMANENT DATA LOSS FOR AN UNBOUNDED DEFERRAL, AND THE
     * SECOND HALF OF THAT TRADE WAS NEVER PAID.** A `Skipped` row is
     * outstanding, so a list nobody ever supplies a `region_code` for is
     * re-marked every {@see self::BARREN_PASS_MINUTES} minutes for ever: the
     * campaign never closes, nothing is ever sent, and **nobody is ever told**.
     * That is not better than losing the audience — it is the same outcome
     * with no record of it.
     *
     * ⚠️ **FOURTEEN DAYS FOLLOWS `ReviewRouter::MAX_DEFERRAL_DAYS`' PRECEDENT
     * RATHER THAN ITS FIGURE**, which is three. An invite answers a
     * just-completed visit and goes stale in days; a reactivation campaign
     * addresses a customer who last came in months ago, so a fortnight of quiet
     * hours, a paused account or an unloaded register is a delay rather than a
     * missed moment. ⚠️ **It also has to outlast the thing it is waiting for**:
     * `StateUnknown` needs the owner to fix a spreadsheet and re-import, and
     * three days would give up while they were still finding the file.
     *
     * ⚠️ **A CONSTANT RATHER THAN A REGISTRY KEY**, on `BARREN_PASS_MINUTES`'
     * own argument one field up: a knob here is a control whose only effect is
     * how long a stuck campaign waits before admitting it is stuck, and every
     * toggle is a future support ticket.
     */
    public const int MAX_DEFERRAL_DAYS = 14;

    /**
     * Whether a message left this machine during this pass.
     *
     * ⛔ **THE ONLY THING THAT EARNS THE OWNER'S SENTENCE, AND IT EXISTS
     * BECAUSE THERE WAS NOTHING ELSE TO ASK** (7327, closed here).
     * {@see self::activityAction()} used to return `ReactivationCampaignSent`
     * unconditionally, and `AutopilotJob::recordActivity()` runs after *either*
     * arm returns — so *"Reached out to customers you had not heard from"* was
     * filed on a campaign that could not be loaded, on an empty batch, on a
     * pass where every recipient was skipped mid-flight, on one where every one
     * was refused, on a carrier outage, and on the hand-off, one row above
     * *"your campaign is waiting"*. This job's whole member list was two
     * constants and one readonly property; `$counts` is a local in
     * {@see self::execute()}. So the answer had to become state, and
     * `AutopilotJob::activityAction()`'s own lint already says which state:
     * *"gate it on the flag that says the work actually happened, never on the
     * idempotency claim"* (7224).
     *
     * ⚠️ **FALSE IS THE DEFAULT AND IT IS THE UNHELPFUL ONE ON PURPOSE**, which
     * is {@see self::claimIsSpent()}'s argument one axis over. Every path that
     * does not reach the assignment — the not-runnable return, a throw, and
     * both of `handoff()`'s arms — leaves this false and files nothing. Silence
     * about work that happened is recoverable from `automation_runs`, which is
     * the exhaustive record; a claim about work that did not happen is not
     * recoverable from anywhere. **A flag defaulting the convenient way would
     * be the booby trap `claimIsSpent()` refuses to build, in the next method
     * along.**
     *
     * ⚠️ **PER PASS, NOT PER CAMPAIGN, AND THE COST IS STATED RATHER THAN
     * HIDDEN.** `campaigns.batch_size` seeds 100, so a five-thousand-contact
     * campaign files fifty of these — the cadence
     * {@see self::reportWhoCouldNotBeReached()} explicitly refuses for its own
     * sentence (*"an owner told about eleven contacts fourteen times learns
     * nothing and stops reading"*). Once-at-close was considered and refused:
     * a campaign only closes when nothing is outstanding, so a pass that texted
     * a hundred members of the public and then stalled on `unknown` rows would
     * reach the feed **never**, against `29` §2 rule 42's sixty seconds. Fifty
     * true rows is a cadence question; one missing row is a marketing send
     * nobody was told about. **The cadence is raised at 7448, not fixed here.**
     */
    private bool $aMessageLeftThisPass = false;

    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $campaignId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'react.campaign_pass';
    }

    /**
     * ⚠️ **NULL, AND THE BASE CLASS DOCUMENTS WHY THAT IS RIGHT FOR REPEATING
     * WORK.** A pass is meant to run again — that is how a campaign of five
     * thousand finishes — so a key would make the second pass collide with the
     * first and the campaign would stop after one batch, silently, with a run
     * row that says it succeeded.
     *
     * ⚠️ **THE IDEMPOTENCY THAT MATTERS IS PER SEND, NOT PER PASS**, and it is
     * two layers down: `SendKey` makes a retried send return `Duplicate` and
     * write nothing, and the recipient row's own status stops the ordinary
     * second attempt before it gets that far. Two passes racing therefore cost a
     * duplicate outcome, never a duplicate message or a duplicate debit.
     *
     * ⛔ **THAT PARAGRAPH'S SECOND CLAUSE WAS TRUE OF THE ORDINARY ATTEMPT AND
     * SILENTLY UNTRUE OF THE RETRIED ONE, AND IT STAYED THAT WAY FOR TEN DAYS —
     * BOTH READINGS KEPT AND DATED, 2026-08-21** (7069, closed at 7180). *"The
     * recipient row's own status"* only stops a second attempt if something
     * wrote a status, and until 7180 the **only** writer of that row was
     * {@see self::record()}, which a `TextNotDeliverable` skips entirely. So the
     * row stayed `Pending`, {@see self::batch()} re-selected it, and the
     * `SendKey` layer named one sentence earlier had already been rolled back by
     * `PlatformMessageSender::transact()` on the way out — deliberately, for
     * callers that hold a run claim, and this one holds none. **Two passes cost
     * a second marketing text to a member of the public.**
     *
     * ✅ **THE PARAGRAPH IS TRUE NOW AND IT IS TRUE BECAUSE OF WORK, NOT BECAUSE
     * IT WAS RE-READ.** {@see self::answerTransportFailure()} is the second
     * writer, so every path out of a send attempt now writes a status —
     * `Unknown` where the outcome cannot be established, `Failed` where the
     * transport can prove nothing left this machine. **The unkeyed design is
     * unchanged and must stay unchanged**: `RunDueCampaigns` depends on it, and
     * a run-level key would stop a five-thousand-contact campaign after one
     * batch with a run row saying it succeeded.
     */
    protected function idempotencyKey(): ?string
    {
        return null;
    }

    /**
     * ⚠️ **ANSWERED EXPLICITLY BECAUSE THE LINT DEMANDS IT, AND THE HONEST ANSWER
     * IS THAT IT CANNOT MATTER HERE.** `ConventionsTest`'s rule is scoped to any
     * job that declares an `idempotencyKey()` at all, deliberately: the failure
     * it exists to stop is a keyed job *inheriting* the base class's `true` in
     * silence, which is how `SendReviewInviteJob` shipped with a dead retry
     * ladder. This job's key is null, so `releaseUnearnedClaim()` returns before
     * it touches anything and either answer produces identical behaviour.
     *
     * ⛔ **SO IT RETURNS THE BASE CLASS'S VALUE RATHER THAN THE CONVENIENT ONE.**
     * `false` would look thoughtful and would be a booby trap: the day somebody
     * gives this job a real key — one pass per campaign per hour, say — the
     * release would already be wired, and a pass that sent four hundred messages
     * and then threw on the four hundred and first would hand its claim back and
     * be retried from the top. `SendKey` makes the individual sends duplicates,
     * but the trip check, the arbiter and the media render would all run again.
     * The unhelpful default is the safe one, which is the base class's own
     * argument, and this restates it rather than quietly reversing it.
     */
    protected function claimIsSpent(): bool
    {
        return true;
    }

    /**
     * ⛔ **NULLABLE, BECAUSE TWELVE ARMS REACH `recordActivity()` AND ONE OF
     * THEM DID THE THING THE SENTENCE NAMES** (7327, 7440). The declaration
     * itself was the defect: `: AutopilotActionType` is a promise that every
     * path through this job earns *"Reached out to customers you had not heard
     * from"*, and no body could have prevented it —
     * `AutopilotJob::recordActivity()` runs unconditionally once either arm
     * returns. That is `ConventionsTest`'s *"no autopilot job types itself out
     * of being able to say nothing"*, and this file was the equality it was
     * born carrying.
     *
     * ⚠️ **`sent` IS THE WHOLE CONDITION, AND THE TWO NEAR MISSES ARE THE
     * INTERESTING PART.** `duplicate` moves the campaign on and
     * {@see self::passReachedNobody()} deliberately counts it as progress — but
     * a `SendKey` collision means the message belongs to an **earlier** send,
     * and the pass that made that send is the one that filed this row. Counting
     * it here would file the sentence twice for one text. And `unknown` is
     * 7183's population: the message may be on the handset and we cannot say,
     * which is the one thing this sentence must not assert on a guess —
     * {@see self::reportWhoCouldNotBeConfirmed()} already gives those contacts
     * their own honest sentence at close.
     *
     * ⚠️ **BOTH OF `handoff()`'S ARMS ANSWER NULL AND THAT IS THE POINT OF THE
     * FLAG BEING SET IN `execute()` ONLY.** A hand-off marks nothing and sends
     * nothing; its own `OwnerActionNeeded` row is what the owner gets, and it
     * used to sit one line *below* a claim that the campaign had already gone
     * out.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->aMessageLeftThisPass
            ? AutopilotActionType::ReactivationCampaignSent
            : null;
    }

    /**
     * The run row names the campaign, which is what makes the sweep safe.
     *
     * ⛔ **WITHOUT THIS, `campaigns:run-due` CANNOT TELL A LIVE CAMPAIGN FROM A
     * STALLED ONE, AND THE FAILURE COMPOUNDS.** The sweep would dispatch a pass
     * beside the one this job already re-queued; both would finish, both would
     * re-queue, and the number of chains would double every pass until the queue
     * was full of one campaign re-marking the same rows. `automation_runs` is
     * already the exhaustive record of attempts and `AutopilotJob::input()` is
     * protected for exactly this — its own docblock says a sweeper *"needs to
     * count this automation's previous attempts **for one row**, which is not
     * answerable from a business id and a location id"*.
     *
     * ⚠️ **AND IT IS THE ONLY PLACE THE CAMPAIGN ID IS RECORDED.**
     * `AutopilotJob::recordSkip()` writes no `input` at all, so a run stopped by
     * the kill switch or a tenant pause is invisible to that query — which is
     * why `RunDueCampaigns` refuses those tenants whole rather than relying on
     * the staleness window to notice.
     *
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return [...parent::input(), 'campaign_id' => $this->campaignId];
    }

    /**
     * ⚠️ **A CAMPAIGN CANNOT BE HANDED OFF, AND SAYING SO IS THE HONEST
     * ANSWER.** `29` §2 rule 44 wants both paths built in the same ticket, and
     * its worked example is the review engine running with zero GBP API access —
     * a hand-off there produces something the owner can do by hand. There is no
     * by-hand equivalent of texting five thousand people, and the one the record
     * once had (T131's manual-link reactivation) was **superseded by R1**, which
     * is why this does not quietly rebuild it.
     *
     * So the hand-off leaves every outstanding recipient outstanding — nothing
     * is marked as anything — and files an owner-action item. A hand-off that
     * consumed the audience would look like a campaign that ran.
     *
     * ⛔ **AND UNTIL 7440 IT *DID* LOOK LIKE ONE, ONE ROW HIGHER UP.** Neither
     * arm here sets {@see self::$aMessageLeftThisPass}, so
     * {@see self::activityAction()} now answers null for both — where before,
     * `AutopilotJob::recordActivity()` filed *"Reached out to customers you had
     * not heard from"* immediately after this returned, and the
     * campaign-not-loadable arm below filed it with **nothing else in the feed
     * to qualify it**. The `OwnerActionNeeded` row this method writes is the
     * whole of what an owner gets from a hand-off and is load-bearing in two
     * lints; it is unchanged.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): ?array
    {
        $campaign = $this->campaign();

        if (! $campaign instanceof Campaign) {
            return null;
        }

        app(ActivityService::class)->record(
            AutopilotActionType::OwnerActionNeeded,
            $this->locationId,
            ['campaign_id' => $campaign->getKey()],
            'Text messaging is switched off, so your campaign is waiting',
        );

        return ['handed_off' => true, 'campaign_id' => $campaign->getKey()];
    }

    /**
     * Whether the carrier path is available at all.
     *
     * Asked once, before the pass, and it is *not* the mid-flight check — that
     * is {@see self::stopCondition()}, which is asked before every recipient and
     * covers the same switch plus the two tenant ones.
     */
    protected function canExecute(): bool
    {
        return (bool) app(DefaultsRegistry::class)->value('sms.enabled');
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        $campaign = $this->campaign();

        if (! $campaign instanceof Campaign) {
            // Cancelled, completed, or not this tenant's. Not an error: the run
            // row records that a pass was attempted and found nothing to do.
            return ['sent' => 0, 'reason' => 'not runnable'];
        }

        if ($campaign->status === CampaignStatus::Scheduled) {
            $campaign->status = CampaignStatus::Running;
            $campaign->started_at ??= now();
            $campaign->save();
        }

        $counts = ['sent' => 0, 'refused' => 0, 'duplicate' => 0, 'skipped' => 0, 'failed' => 0, 'unknown' => 0];

        foreach ($this->batch($campaign) as $recipient) {
            $stop = $this->stopCondition($campaign);

            if ($stop !== null) {
                // ⛔ **THE MID-FLIGHT TRIP.** Marked rather than left pending, so
                // an operator reading the rows can tell "we got here and
                // deliberately did not" from "not yet".
                $this->markSkipped($recipient, $stop);
                $counts['skipped']++;

                continue;
            }

            $outcome = $this->sendTo($campaign, $recipient);
            $counts[$outcome]++;

            if ($outcome === 'unknown') {
                // ⛔ **A SEND WITH NO OUTCOME ENDS THE PASS, AND THE REASON IS
                // ARITHMETIC RATHER THAN CAUTION** (7183). Every arm that
                // answers `unknown` is a *slow* arm by construction — a read
                // timeout, a 5xx, a 200 whose body could not be read — while
                // every arm that answers `failed` is refused before or at the
                // socket and costs nothing. So an Infobip outage met by a
                // runner that carried on would spend `services.infobip.timeout`
                // seconds per remaining recipient, each inside a held database
                // transaction, and would convert every one of them into a
                // permanently unconfirmable row on a single bad afternoon.
                //
                // ⚠️ **THE REMAINING RECIPIENTS ARE LEFT EXACTLY AS THEY WERE**
                // — `Pending`, not `Skipped`. Nothing was decided about them
                // and `Skipped` means *we got here and deliberately did not*.
                // They are still outstanding, so the pass below re-queues and
                // {@see self::BARREN_PASS_MINUTES} is the backoff.
                break;
            }
        }

        // ⛔ **THE ONE ASSIGNMENT, AND IT IS DELIBERATELY THE LAST THING BEFORE
        // THE CLOSE.** {@see self::$aMessageLeftThisPass} argues why `sent` and
        // nothing else. Set from the counter rather than inside the loop so
        // there is a single place to read against the twelve arms above, and
        // set here rather than in `closeIfFinished()` because a pass that
        // re-queues has still sent whatever it sent.
        $this->aMessageLeftThisPass = $counts['sent'] > 0;

        $this->closeIfFinished($campaign, $this->passReachedNobody($counts));

        return $counts;
    }

    /**
     * Whether this pass got through nobody at all, which is what the barren
     * backoff is for.
     *
     * ⛔ **THE TRANSPORT COUNTERS BELONG IN HERE AND THE FIX WOULD HAVE SHIPPED
     * A DEFECT WITHOUT THEM** (7184). The predicate this replaces was
     * `sent + duplicate === 0 && skipped > 0`, and until 7180 a transport
     * failure could not reach it at all — the throw took the whole pass with
     * it. Catching it turns a carrier outage into a pass whose only movement is
     * `failed`, which the old expression reads as *making progress* and
     * re-queues in **one minute**: a fortnight of the 2687 ceiling at one pass a
     * minute is twenty thousand attempts against a carrier that is already
     * refusing, where fifteen minutes is thirteen hundred.
     *
     * ⚠️ **`refused` IS STILL DELIBERATELY EXCLUDED**, which is the behaviour
     * this method inherits rather than a new judgment: a refusal is terminal, so
     * a pass that refused somebody has permanently reduced the outstanding set
     * and the campaign really is moving.
     *
     * @param  array<string, int>  $counts
     */
    private function passReachedNobody(array $counts): bool
    {
        if ($counts['sent'] + $counts['duplicate'] > 0) {
            return false;
        }

        return $counts['skipped'] + $counts['failed'] + $counts['unknown'] > 0;
    }

    /**
     * The stop condition in force right now, or null.
     *
     * ⚠️ **THE CHANNEL SWITCH IS ASKED FIRST, AND THE ORDER IS `AutopilotJob`'S
     * OWN REASONING.** Ours must be answerable when the tenant's cannot be read
     * at all; the suspension is asked before the pause because a tenant can be
     * both and the operative reason is ours — an operator reading a skipped row
     * needs the condition they can act on.
     *
     * ⛔ **THE GUARD IS THE FIFTH AND IT IS NOT OPTIONAL.** See the class
     * docblock for why it is last rather than first, and for the defect its
     * absence was.
     */
    private function stopCondition(Campaign $campaign): ?string
    {
        if (! app(DefaultsRegistry::class)->value('sms.enabled')) {
            return 'text messaging is switched off';
        }

        if (app(TenantSuspension::class)->isCurrentTenantSuspended()) {
            return 'tenant suspended';
        }

        if (app(TenantPause::class)->isCurrentTenantPaused()) {
            return 'tenant paused';
        }

        // ⛔ **DECISION 3310'S THREE PRECONDITIONS, AND THEY ARE ASKED HERE
        // RATHER THAN AT CONFIRMATION FOR THE REASON EVERYTHING ELSE IN THIS
        // METHOD IS.** *"They need to buy credit and finish 10dlc on there
        // number they can not use a go ai ez number for this they must have
        // there own"* — a carrier can refuse a filing, a number can be
        // quarantined and a purchased balance can run out **while this loop is
        // running**, and a broadcast that checked once at enqueue would
        // authorise every remaining message on a reading taken before the first
        // one went out. That is 2117's rejected implementation exactly.
        //
        // ⚠️ **BEFORE THE GUARD AND AFTER THE PAUSE, WHICH IS THIS METHOD'S OWN
        // ORDERING RULE.** All three are cheap indexed reads and none of them
        // can *create* anything; the guard is last because it recomputes a rate
        // and may write a pause. ⚠️ **It answers null for a reactivation**, so
        // the ordering costs the existing lane one enum comparison.
        $broadcast = app(BroadcastPreconditions::class)->refusalFor($campaign->kind);

        if ($broadcast !== null) {
            return $broadcast;
        }

        // ⛔ **THE PER-MESSAGE CONTAINMENT CHECK — 2102, 2113, 2117, 2182.**
        // Consulted here, between recipients, so a campaign whose complaint rate
        // crosses the threshold on its two-hundredth message stops on its
        // two-hundred-and-first. `SendingHealth`'s counters move while this loop
        // runs; a check hoisted out of it would read them once, before anything
        // had been sent, and authorise every remaining message on that reading.
        //
        // ⚠️ The reason is turned into an operator's sentence rather than
        // recorded on the row: these are tenant-wide states, and a
        // `SendRefusalReason` on a recipient row is a statement about that
        // contact. See the class docblock on `Skipped` vs `Refused`.
        $refusal = app(SendingGuard::class)->refusalFor(OutreachChannel::Sms);

        if ($refusal instanceof SendRefusalReason) {
            return $refusal === SendRefusalReason::GlobalHalt
                ? 'all platform sending is halted'
                : 'this account\'s sending is paused';
        }

        return null;
    }

    /**
     * The campaign, if this tenant has one that is still sendable.
     */
    private function campaign(): ?Campaign
    {
        $campaign = Campaign::query()->find($this->campaignId);

        if (! $campaign instanceof Campaign) {
            return null;
        }

        // ⚠️ **CONFIRMED, CHECKED HERE TOO.** The CHECK constraint makes an
        // unconfirmed sendable status impossible and this asks anyway, because
        // 398's shape is a guard whose only proof is another guard: if the
        // constraint were ever relaxed, this is the line that still refuses.
        if (! $campaign->status->isSendable() || $campaign->confirmed_at === null) {
            return null;
        }

        return $campaign;
    }

    /**
     * The next recipients to attempt, oldest first.
     *
     * @return Collection<int, CampaignRecipient>
     */
    private function batch(Campaign $campaign): Collection
    {
        return CampaignRecipient::query()
            ->where('campaign_id', $campaign->getKey())
            ->whereIn('status', [
                CampaignRecipientStatus::Pending,
                CampaignRecipientStatus::Skipped,
                CampaignRecipientStatus::Failed,
            ])
            ->with('customer')
            // `id`, never `created_at`: it is nullable and Postgres sorts NULL
            // first on a DESC ordering — decision 289's shape, and here it would
            // decide who gets messaged first.
            //
            // ⚠️ **AND THE KEYS THIS SORTS ARE DEFINED RATHER THAN ARBITRARY,
            // WHICH IS A FACT ABOUT ANOTHER FILE AND IS THE POINTER THIS ONE
            // OWED** (11366). `Services\Campaigns\Campaigns::enrol()` is the
            // sole writer of `campaign_recipients`, and it creates them while
            // walking `audienceQuery(…)->orderBy('id')`. **That line arrived at
            // 11085 and until then neither that query nor
            // `DormancySegment::apply()` carried any `ORDER BY` at all** — so
            // this sort was **faithful rather than immune**: it preserved
            // whatever order Postgres returned, exactly, and the sentence above
            // described a deterministic sort over arbitrary keys.
            //
            // ⛔ **THE POINTER RAN ONE WAY AND THE COST OF THAT IS ON THE
            // RECORD.** `Campaigns::enrol()` carries a full pointer *to* this
            // method, quoting the comment above verbatim; this method carried
            // none back, and a reader arriving here could not tell the two
            // cases apart from anything in this file. 10979 diagnosed the
            // resulting test failure twice and was wrong both times — it named
            // a path that does not exist and a class outside the failing test's
            // code path — rather than the enroller.
            //
            // ⚠️ **WHAT THE ORDER ACTUALLY IS: enrolment pass, then customer
            // `id` within it.** A single pass sends to the oldest customers
            // first. A later pass appends, so somebody enrolled second sits
            // behind everybody enrolled first whatever their own id —
            // `firstOrCreate` against the unique index is what makes a repeated
            // pass add only the newly-qualifying and touch nobody else.
            //
            // ⚠️ **AND THE PROOF IS A TEST RATHER THAN A LIVE PATH.**
            // `Feature\Campaigns\CampaignRunnerTest`'s ordered two-element
            // destination assertion reddens naming both phone numbers when
            // `enrol()`'s sort is reversed, and that is the whole of the
            // evidence: `ops:method-callers` scores `enrol()` as having **no
            // caller in `app/`** — fifty-odd in `tests/` and none here — raised
            // in the method-census slice (9160–9163) and deliberately not acted
            // on. **Nothing in `app/` materialises an audience today**, so this
            // ordering is exercised only by the suite.
            ->orderBy('id')
            ->limit(app(DefaultsRegistry::class)->int('campaigns.batch_size'))
            ->get();
    }

    /**
     * Attempt one recipient. Returns the counter to increment.
     */
    private function sendTo(Campaign $campaign, CampaignRecipient $recipient): string
    {
        $customer = $recipient->customer;

        if (! $customer instanceof Customer) {
            return $this->answerRefusal($recipient, SendRefusalReason::NoIdentifier);
        }

        // ⛔ **MARKETING, NOT TRANSACTIONAL, AND THE CHOICE IS DECISION 2100.**
        // *"An established business relationship is not prior express written
        // consent for marketing texts … the attestation records who carries the
        // basis, it does not create one, and reactivation is marketing."* This
        // one argument is what puts the Do Not Call registers, the state
        // mini-TCPA windows and the recipient-local quiet-hours floor in front
        // of every send this job makes.
        $permit = app(ConsentService::class)->decide($customer, OutreachChannel::Sms, OutreachPurpose::Marketing);

        if (! $permit->permit instanceof SendPermit) {
            // ⛔ **CLASSIFIED, NEVER UNIFORMLY REFUSED — 2570.** Every refusal
            // used to terminate the recipient, so a campaign started inside
            // quiet hours consumed its whole audience on the first pass and sent
            // nothing. See `SendRefusalReason::isTemporary()`.
            return $this->answerRefusal($recipient, $permit->reason ?? SendRefusalReason::NoConsentRecord);
        }

        // `S1`. Asked after consent so a suppressed contact is reported as
        // suppressed rather than as a collision — the durable refusal beats the
        // one that will have moved by tomorrow, which is 1620's ordering rule.
        $arbiter = app(SendCollisionArbiter::class);

        if (! $arbiter->allows($customer, OutreachChannel::Sms, $arbiter->classOf($campaign))) {
            // ⚠️ **SKIPPED, NOT REFUSED, BECAUSE IT IS TRUE AGAIN TOMORROW.** A
            // refusal is terminal on this row and would drop the contact from
            // the campaign for the sake of one busy afternoon.
            $this->markSkipped($recipient, 'another message reached this contact today');

            return 'skipped';
        }

        try {
            $message = $this->compose($campaign, $recipient, $customer, $permit->permit);
        } catch (MessageCannotBeComposed $e) {
            // One contact's name pushed it over the budget, or their record
            // cannot be rendered. The template itself was already proved at
            // confirmation time, so this is about this person.
            //
            // ⛔ **THIS BRANCH SAID "Refused individually" AND CALLED
            // `markSkipped()`, WHICH IS THE OPPOSITE THING** — 2589 caught the
            // contradiction and 2687 rules on it. `Skipped` is outstanding, so a
            // contact whose name will never fit was retried every fifteen
            // minutes for ever, exactly like the `StateUnknown` case that ruling
            // is named after. **It takes the same ceiling**, which is why the
            // deferral clock is asked here too rather than the row being refused
            // outright: a name is editable, and the fortnight is the window in
            // which somebody might.
            Log::info('A campaign message could not be composed for one recipient.', [
                'campaign_id' => $campaign->getKey(),
                'reason' => $e->getMessage(),
            ]);

            if ($this->deferralIsExhausted($recipient)) {
                $this->markRefused($recipient, SendRefusalReason::MessageTooLong);

                return 'refused';
            }

            $this->markSkipped($recipient, 'the message did not fit for this contact');

            return 'skipped';
        }

        try {
            $outcome = app(MessageSender::class)->send($message);
        } catch (TextNotDeliverable $e) {
            // ⛔ **THE CATCH THAT WAS MISSING, AND ITS ABSENCE WAS A SECOND
            // MARKETING TEXT TO A MEMBER OF THE PUBLIC** (7069, closed at
            // 7180). {@see self::record()} is the only writer of this row, so a
            // throw left it `Pending`; `Pending` means *not yet attempted*, so
            // {@see self::batch()} re-selected the same contact on the retry.
            // The `send_key` that would have refused the second attempt was
            // rolled back with the transaction on the way out — deliberately,
            // for callers that hold a run claim — and this job holds none.
            return $this->answerTransportFailure($recipient, $e, $message->key->value);
        }

        return $this->record($recipient, $outcome);
    }

    /**
     * Build the one message this recipient gets.
     *
     * ⛔ **ONE `OutboundMessage` FOR THE SMS AND ITS PICTURE — AND THIS SENTENCE
     * ENDED "WHICH IS WHAT MAKES R9'S PAIR COST ONE CREDIT" UNTIL 9182.** It
     * costs **two**: one for the text, one for the media. The media still rides
     * the same object, so there is one key, one send and one debit — of two
     * units — rather than two sends and a pricing rule in a runner to reconcile
     * them.
     *
     * ⚠️ **AND THIS IS THE METHOD THAT PUTS THE PICTURE THERE**, so it is the
     * one place in `app/` that can make a send cost two. `CampaignMedia::urlFor()`
     * returning null is a one-credit text; returning a URL is a two-credit
     * message. Nothing here decides that — it is read off the row downstream —
     * but this is where it becomes true.
     *
     * @throws MessageCannotBeComposed
     */
    private function compose(
        Campaign $campaign,
        CampaignRecipient $recipient,
        Customer $customer,
        SendPermit $permit,
    ): OutboundMessage {
        $location = $campaign->location;
        $business = $location instanceof Location
            ? $location->businessName()
            : (string) $campaign->business?->name;

        $text = app(ReactComposer::class)->compose(
            template: $campaign->body_template,
            businessName: $business,
            contactName: $customer->name,
            link: $campaign->link,
        );

        $media = app(CampaignMedia::class)->urlFor($campaign, $recipient, $customer);

        // ⛔ **THE OCCASION IS NEVER A CONSTANT.** `SendKey`'s own docblock: a
        // caller passing one collapses every message it ever sends a contact
        // into a single key, and the second is dropped as a duplicate with no
        // error anywhere. This lane sends many messages to the same person over
        // time, so the campaign id is what keeps them apart — and the step is
        // spelled out for the day a campaign gains a second one.
        //
        // ⚠️ **THE STRING MOVED ONTO THE MODEL AT P20 AND IS UNCHANGED.**
        // `CampaignReplyResolver` has to reproduce it to say which send a reply
        // is answering, and a second spelling here would file replies under an
        // occasion no send ever used.
        $key = SendKey::for($permit, $campaign->sendOccasion());

        return OutboundMessage::for(
            permit: $permit,
            body: $text->body,
            key: $key,
            purpose: OutreachPurpose::Marketing,
            mediaUrls: $media === null ? [] : [$media],
        );
    }

    /**
     * Write the outcome on the row, and answer which counter moved.
     *
     * ⛔ **`wasSent()` IS FALSE FOR A DUPLICATE AS WELL AS A REFUSAL**, which is
     * the trap the send-driver contract names first. A runner treating a
     * duplicate as a send would file a second row and — if the debit lived here
     * — charge a second credit against one delivered message.
     */
    private function record(CampaignRecipient $recipient, SendOutcome $outcome): string
    {
        $recipient->send_key = $outcome->key->value;

        if ($outcome->wasSent()) {
            $recipient->status = CampaignRecipientStatus::Sent;
            $recipient->refusal_reason = null;
            $recipient->provider_message_id = $outcome->providerMessageId;
            $recipient->sent_at = now();
            $recipient->save();

            return 'sent';
        }

        // ⚠️ **THE DRIVER'S REFUSALS ARE CLASSIFIED TOO, AND THIS SIDE IS THE
        // ONE THAT WILL MEET `InsufficientCredit`.** A balance exhausted on the
        // four-hundredth message must not terminate the remaining audience —
        // rule 43 degrades gracefully, and a top-up should resume the campaign
        // rather than require it to be rebuilt.
        if ($outcome->reason instanceof SendRefusalReason) {
            return $this->answerRefusal($recipient, $outcome->reason, $outcome->key->value);
        }

        // A duplicate. Nothing new left the system; the row records that this
        // send belongs to an earlier one rather than pretending it happened
        // twice.
        $recipient->status = CampaignRecipientStatus::Duplicate;
        $recipient->provider_message_id = $outcome->providerMessageId;
        $recipient->save();

        return 'duplicate';
    }

    /**
     * Write one refusal on the row the way that refusal deserves, and answer
     * which counter moved.
     *
     * ⛔ **THE WHOLE OF DECISION 2570 IS THIS ONE BRANCH.** Before it, every
     * refusal reached {@see self::markRefused()} and `Refused` is terminal —
     * {@see self::batch()} selects `Pending | Skipped | Failed` and
     * {@see CampaignRecipientStatus::isOutstanding()} answers false for a
     * refused row. So a reactivation campaign started at 21:30 met
     * `SendRefusalReason::QuietHours` on every contact, marked every one of them
     * terminally refused, sent **nothing**, and closed with no recoverable list —
     * over a condition that was false again by breakfast. `StateUnknown` burned
     * any imported list with no state column the same way.
     *
     * ⚠️ **THE CLASSIFICATION LIVES ON THE ENUM, NOT IN THIS `match`.** The same
     * distinction is owed by every other sender — `ReviewInviteSender` loses a
     * quiet-hours invite outright today (2207) — and a second copy of the list
     * here is how the two would stop agreeing.
     *
     * ⚠️ **A TEMPORARY REFUSAL RECORDS NO REASON ON THE ROW, AND THE DATABASE
     * INSISTS.** `campaign_recipients_reason_belongs_to_a_refusal` is
     * `(refusal_reason IS NOT NULL) = (status = 'refused')`, so the reason
     * reaches the log rather than the column — the same place, and for the same
     * reason, as 2454's four tenant-wide stop conditions.
     */
    private function answerRefusal(
        CampaignRecipient $recipient,
        SendRefusalReason $reason,
        ?string $key = null,
    ): string {
        if (! $reason->isTemporary()) {
            $this->markRefused($recipient, $reason, $key);

            return 'refused';
        }

        // ⛔ **AND A TEMPORARY REFUSAL IS NOT A PERMANENT DEFERRAL — 2687.**
        // Everything below this line is the second half of 2570's trade, which
        // 2589 recorded as owed and nobody paid: `Skipped` is outstanding, so
        // without a ceiling this branch re-marks the same row every fifteen
        // minutes for ever and the campaign never closes and never reports.
        if ($this->deferralIsExhausted($recipient)) {
            $this->markRefused($recipient, $reason, $key);

            return 'refused';
        }

        // ⚠️ **THE REASON IS AN OPERATOR'S SENTENCE, NEVER A CONTACT DETAIL.**
        // `SendRefusalReason` names a rule and never a person — that is its own
        // docblock's promise — so the case value is safe here where a phone
        // number would not be.
        $this->markSkipped($recipient, "refused for now: {$reason->value}");

        return 'skipped';
    }

    /**
     * Write the outcome of a send that threw on the wire, and answer which
     * counter moved.
     *
     * ⛔ **ONE BIT DECIDES THIS AND IT IS THE ONLY BIT THERE IS.**
     * `TextNotDeliverable::$mayHaveReachedCarrier` is *"not positively known to
     * have stayed here"*, never *"it was sent"* — the property is named for the
     * direction it fails in, and this method reads it in exactly that
     * direction. **`true` ends the contact's campaign without a claim about
     * what happened; `false` is a failure this application can prove was
     * harmless, so the contact stays outstanding and is attempted again.**
     *
     * ⛔ **THIS IS THE FOURTH READER OF THAT FLAG AND THE LINT THAT PINNED IT TO
     * THREE WAS WIDENED ON PURPOSE** (7185). Its stated fear was *"a fourth
     * reader is a fourth interpretation"*, and the interpretation here is the
     * same one, one table over: the other three set `$claimSpent` so the queue
     * does not retry a job, and this sets a recipient status so the next pass
     * does not re-select a contact. **The lint's old title — "only a job that
     * holds an idempotency claim" — is precisely why this reader had to exist**:
     * this job deliberately holds none ({@see self::idempotencyKey()}), so the
     * guard the other three lean on is not available to it and the row is the
     * only place the answer can live. 7069 named that in as many words.
     *
     * ⚠️ **NOT READING THE FLAG WAS CONSIDERED AND IS WORSE.** Treating every
     * `TextNotDeliverable` as unknown never texts anybody twice and would burn
     * the entire audience of every campaign on the first pass of a deployment
     * with no Infobip credential — 7070's *"most load-bearing `false` of the
     * four"*, which is raised before a request object exists at all.
     *
     * ⚠️ **`ChannelUnavailable` IS THE REASON AT THE CEILING, AND IT IS THE
     * EXISTING CASE RATHER THAN A NEW ONE** (7186). Its own docblock already
     * covers *"a fact about **our** transport, never about the recipient"*, its
     * `ownerSentence()` is *"text messaging was unavailable"*, and it is
     * classified temporary — which is what makes {@see self::deferralIsExhausted()}
     * the right gate here rather than a second ladder. A new case would be this
     * enum claiming a distinction the one bit above cannot make.
     */
    private function answerTransportFailure(
        CampaignRecipient $recipient,
        TextNotDeliverable $failure,
        string $key,
    ): string {
        if ($failure->mayHaveReachedCarrier) {
            $this->markUnknown($recipient, $failure, $key);

            return 'unknown';
        }

        // ⛔ **2687'S CEILING, AND IT IS WHAT CLOSES THE SECOND DEFECT.**
        // `TextNotDeliverable::notAccepted('REJECTED')` — a blocked handset, or
        // a sender the carrier has not registered — is provably harmless and
        // therefore lands here, and it is also true again on every future pass.
        // Without the ceiling the campaign never closes and the owner is never
        // told, which is 2687's failure mode rebuilt on the transport axis.
        //
        // ⚠️ **AND REFUSING IT ON THE FIRST ATTEMPT WOULD BE 2570's DEFECT.**
        // The vendor's one `REJECTED` covers a permanently blocked number *and*
        // an unregistered sender, and the second is the owner's to fix — giving
        // up on the audience immediately would mean fixing the 10DLC filing no
        // longer recovered the campaign. The fortnight is the honest answer to
        // a condition this application cannot classify.
        if ($this->deferralIsExhausted($recipient)) {
            $this->markRefused($recipient, SendRefusalReason::ChannelUnavailable, $key);

            return 'refused';
        }

        $this->markFailed($recipient, $failure, $key);

        return 'failed';
    }

    /**
     * Has this recipient been put off for longer than we are willing to?
     *
     * ⚠️ **IT STAMPS THE CLOCK ON THE WAY PAST, WHICH IS WHY IT IS A PREDICATE
     * WITH A SIDE EFFECT.** `SendingGuard::shouldTrip()` makes the same trade
     * and states the same reason: the alternative is a caller that asks and then
     * separately remembers to start the clock, which is one forgotten line away
     * from a ceiling that is never reached because it was never started.
     *
     * ⚠️ **THE FIRST DEFERRAL NEVER COUNTS AS EXHAUSTED**, whatever the clock
     * says. A row reaching this method with no `first_deferred_at` has just been
     * put off for the first time — reading the ceiling against a null and
     * treating it as "long ago" would refuse the entire audience on the first
     * pass, which is 2570's defect rebuilt by the fix for it.
     */
    private function deferralIsExhausted(CampaignRecipient $recipient): bool
    {
        $first = $recipient->first_deferred_at;

        if (! $first instanceof CarbonInterface) {
            $recipient->first_deferred_at = now();

            return false;
        }

        return $first->lte(now()->subDays(app(DefaultsRegistry::class)->int('campaigns.run.max_deferral_days')));
    }

    private function markRefused(CampaignRecipient $recipient, SendRefusalReason $reason, ?string $key = null): void
    {
        $recipient->status = CampaignRecipientStatus::Refused;
        $recipient->refusal_reason = $reason;
        $recipient->send_key = $key;
        // The CHECK refuses a refused row that names a message or carries a
        // time, so these are cleared rather than left from an earlier attempt.
        $recipient->provider_message_id = null;
        $recipient->sent_at = null;
        $recipient->save();
    }

    /**
     * The wire refused this message and provably never carried it.
     *
     * ⛔ **THE FIRST WRITER `CampaignRecipientStatus::Failed` HAS EVER HAD**
     * (7181). Until now the case existed, was selected by two queries and set
     * by a factory, and nothing in `app/` ever assigned it — 272's shape inside
     * the enum that describes this exact outcome.
     *
     * ⚠️ **OUTSTANDING, SO THE ROW COMES BACK ROUND**, and the deferral clock
     * has already been stamped by the caller, so it comes back round a bounded
     * number of times.
     *
     * ⚠️ **THE EXCEPTION'S OWN MESSAGE IS SAFE TO LOG AND THAT IS A PROPERTY OF
     * `TextNotDeliverable` RATHER THAN A HOPE.** Its class docblock forbids
     * anything from a vendor response body and anything naming the recipient:
     * *"Fixed labels and vendor status names only."* No number, no name, no
     * campaign contact of any kind reaches this line.
     */
    private function markFailed(CampaignRecipient $recipient, TextNotDeliverable $failure, string $key): void
    {
        $recipient->status = CampaignRecipientStatus::Failed;
        $recipient->refusal_reason = null;
        $recipient->send_key = $key;
        $recipient->save();

        Log::info('A campaign message could not be handed to the carrier.', [
            'campaign_id' => $recipient->campaign_id,
            'why' => $failure->getMessage(),
        ]);
    }

    /**
     * The send was attempted and nobody can say what became of it.
     *
     * ⛔ **THIS ROW IS THE END OF THIS CONTACT'S CAMPAIGN AND IT MAKES NO CLAIM
     * ABOUT WHETHER THEY WERE TEXTED** (7180). It is not `Sent` — there is no
     * carrier handle and there never will be, so no delivery receipt can ever
     * match it. It is not `Refused` — that asserts nothing went. It is not
     * outstanding, because the only way to find out would be to send again.
     *
     * ⚠️ **THE `send_key` IS KEPT, AND IT IS THE ONLY THREAD BACK TO THE
     * ATTEMPT.** `PlatformMessageSender` rolled its `outreach_messages` row
     * back, so this column is the sole surviving record that this send was ever
     * made — and it carries no identifier by construction, which is what makes
     * it safe to keep here. 7066's `GET /sms/3/logs?messageId=…` lookup, if it
     * is ever built, is what would turn this row into an answer.
     * ⛔ **THAT LAST SENTENCE WAS TRUE AND ITS FIRST HALF WAS NOT — CORRECTED
     * 2026-08-22 (7360).** The lookup is built, and **the `send_key` could
     * never have started it**: it is a local claim that has never been sent to
     * any vendor, so Infobip held no value it could be matched against. What
     * makes the row answerable is {@see CampaignRecipient::$send_handle} — the
     * id `InfobipClient` put on the wire as `destinations[].messageId` — and it
     * is written two lines below. The `send_key` is still kept, because it is
     * still the thread back to *our* record of the attempt; it was never the
     * thread back to the carrier's.
     *
     * ⚠️ **THE HANDLE IS NULL WHENEVER THE TRANSPORT HAD NONE TO GIVE**, and
     * that is not a gap to fill in later. The `log` driver mints no handle
     * because it reached nobody, and a transport failure raised before a request
     * body existed has nothing on any wire. A null here means *"this row can
     * never be reconciled"*, which the reconciler reads as *leave it alone*.
     *
     * ⚠️ **A WARNING RATHER THAN AN INFO LINE.** Every other outcome on this
     * job is a decision it made; this is the one it could not.
     */
    private function markUnknown(CampaignRecipient $recipient, TextNotDeliverable $failure, string $key): void
    {
        $recipient->status = CampaignRecipientStatus::Unknown;
        $recipient->refusal_reason = null;
        $recipient->send_key = $key;
        // ⛔ **THE ONE NEW WRITE ON THIS PATH, AND IT CLAIMS NOTHING** (7363).
        // It is not evidence that a message was sent, and no reader may treat it
        // as any — it is the question this row is now able to ask. The proximity
        // lint next door forbids a status, a time or a provider id inside the
        // branch that read `mayHaveReachedCarrier`, and this value is none of
        // the three by design as well as by name: **it is ours, and a provider
        // message id is the carrier's.**
        $recipient->send_handle = $failure->sendHandle;
        // The CHECK constraints permit neither on a row that is not `sent`, and
        // the honest reason is the same one: we have no handle and no time we
        // could stand behind.
        // ⚠️ **THE FIRST CLAUSE IS FALSE ABOUT THE CONSTRAINTS AND IS KEPT AND
        // MARKED, 4368'S RULE (7364).** `campaign_recipients_refusal_sent_nothing`
        // binds `refused` and `campaign_recipients_sent_rows_are_complete` binds
        // `sent`; neither says anything about `unknown`, and `record()` already
        // writes a `provider_message_id` on a `Duplicate` row with a null
        // `sent_at`. **The second clause is the true one and is the whole
        // reason** — this application has no carrier handle for this message and
        // no time it could stand behind, so writing either would be inventing
        // one. It is a convention, and a convention is what the lint next door
        // enforces.
        $recipient->provider_message_id = null;
        $recipient->sent_at = null;
        $recipient->save();

        Log::warning('A campaign message was attempted and its outcome could not be established.', [
            'campaign_id' => $recipient->campaign_id,
            // ⚠️ **WHETHER THE CARRIER CAN BE ASKED, NEVER THE HANDLE ITSELF.**
            // The value is worthless to a reader of this log line and it is the
            // one string that ties a log file to a third party's record of a
            // named recipient. `LogTexter` makes the same call about
            // `has_reference` one file over.
            'can_be_reconciled' => $failure->sendHandle !== null,
            'why' => $failure->getMessage(),
        ]);
    }

    /**
     * @param  string  $why  An operator's sentence. ⚠️ **Never a contact
     *                       detail** — this reaches a log line and, through the
     *                       run row, an Ops screen.
     */
    private function markSkipped(CampaignRecipient $recipient, string $why): void
    {
        $recipient->status = CampaignRecipientStatus::Skipped;
        $recipient->refusal_reason = null;
        $recipient->save();

        Log::info('A campaign recipient was skipped.', [
            'campaign_id' => $recipient->campaign_id,
            'why' => $why,
        ]);
    }

    /**
     * Close the campaign when nothing is outstanding, and re-queue otherwise.
     *
     * ⚠️ **A `Skipped` ROW IS OUTSTANDING, WHICH IS WHY A HALTED CAMPAIGN DOES
     * NOT CLOSE.** It also means a pass that skipped everybody re-queues itself,
     * so the re-queue is deliberately **not** immediate: the next pass is
     * delayed, because a campaign held by a tripped kill switch would otherwise
     * spin a worker as fast as the queue can turn it round.
     *
     * ⛔ **AND ONE MINUTE IS NOT ENOUGH ONCE THE GUARD IS IN THE LOOP, WHICH IS A
     * DEFECT THE CONTAINMENT ITSELF CREATED.** `SendingPause` is released by a
     * person and by nothing else ({@see SendingGuard::resume()} — every
     * `SendingPauseReason::isSelfClearing()` is false), so a tenant tripped at
     * 3am stays tripped until somebody arrives. At a minute a pass, a single
     * paused campaign of a hundred contacts spends the night waking a worker
     * 1,440 times to re-mark the same hundred rows `Skipped` — and it does it
     * per campaign, per tenant, at exactly the moment an incident is in
     * progress. **The backoff is the fix and it belongs to the barren pass, not
     * to the clock**: a pass that got through nobody at all is the shape of a
     * campaign waiting on a human, while a pass that sent even one message is
     * making progress and keeps the short interval.
     *
     * ⚠️ **A CONSTANT RATHER THAN A REGISTRY KEY.** A knob here is a control
     * whose only effect is how hard a stopped thing knocks, which is not a
     * decision anybody should be making at 3am, and `CLAUDE.md`'s standing rule
     * is that every toggle is a future support ticket. Fifteen minutes is short
     * enough that a resumed campaign restarts inside a coffee break and long
     * enough to cut the barren spin by an order of magnitude.
     *
     * @param  bool  $barren  whether this pass reached the end of its batch
     *                        without a single message leaving
     */
    private function closeIfFinished(Campaign $campaign, bool $barren): void
    {
        $outstanding = CampaignRecipient::query()
            ->where('campaign_id', $campaign->getKey())
            ->whereIn('status', [
                CampaignRecipientStatus::Pending,
                CampaignRecipientStatus::Skipped,
                CampaignRecipientStatus::Failed,
            ])
            ->exists();

        if (! $outstanding) {
            $campaign->status = CampaignStatus::Completed;
            $campaign->finished_at = now();
            $campaign->save();

            $this->reportWhoCouldNotBeReached($campaign);
            $this->reportWhoCouldNotBeConfirmed($campaign);

            return;
        }

        self::dispatch($this->businessId, $this->locationId, $this->campaignId)
            ->delay(now()->addMinutes($barren ? app(DefaultsRegistry::class)->int('campaigns.run.barren_pass_minutes') : 1));
    }

    /**
     * Tell the owner who this campaign could not *confirm*, which is a
     * different sentence from the one above.
     *
     * ⛔ **IT IS A SEPARATE ITEM RATHER THAN A GROUP INSIDE
     * {@see self::reportWhoCouldNotBeReached()}, AND THE REASON IS THAT MERGING
     * THEM WOULD MAKE A FALSE STATEMENT** (7187). That method's sentence is
     * *"N contacts could not be sent to"*, and every reason it counts is a
     * refusal — a row the database itself guarantees carried no message
     * (`campaign_recipients_refusal_sent_nothing`). An `Unknown` row is the
     * opposite kind of thing: the message may be on the handset right now.
     * Folding it in would tell an owner nothing went out to somebody who is
     * about to reply to it.
     *
     * ⚠️ **AND IT IS THE ANSWER TO "WHAT DOES THE OWNER GET TOLD".** Before
     * this, the honest answer for an unrecorded send was *nothing at all*: the
     * pass threw, the campaign never closed, and the person who may have
     * received a marketing text was invisible to every reader in the
     * application (7078's closing paragraph, one caller over).
     *
     * ⚠️ **AT CLOSE, ONCE, WITH NO IDENTIFIER AND NO REASON STRING.** The same
     * three rules as the method above, for the same three reasons. There is no
     * cause to name here — that is the whole point of the status — so the
     * sentence names the consequence instead, which is what `22` asks for.
     *
     * ⚠️ **SILENT WHEN THERE WERE NONE**, which is the ordinary case and will
     * stay the ordinary case.
     *
     * ⛔ **"AT CLOSE, ONCE" IS NO LONGER THE WHOLE ACCOUNT — CORRECTED
     * 2026-08-22 (7540, on 7500-7510's work).** `UnknownSendReconciler` now
     * files its own feed corrections when the carrier answers, hours after this
     * sentence was written: *"was sent this message after all"* and *"did not
     * receive this message"*, per campaign, per pass. **So this number is a
     * snapshot that a later writer amends, not a final figure** — which is the
     * honest state, because the alternative was a permanently inflated count
     * that nothing could correct. ⚠️ **WHAT IS STILL OPEN AND IS NOT THIS
     * PARAGRAPH'S TO ANSWER** (7509): whether this sentence should say so —
     * *"we will keep asking for two days"* — rather than leaving an owner to
     * infer it from a correction that may arrive after they have stopped
     * looking. **That is a copy decision with a cost model behind it.**
     */
    private function reportWhoCouldNotBeConfirmed(Campaign $campaign): void
    {
        $total = CampaignRecipient::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('status', CampaignRecipientStatus::Unknown)
            ->count();

        if ($total === 0) {
            return;
        }

        $title = trans_choice(
            '{1} 1 contact may already have this message — we could not confirm it, so we did not '
            .'send again|[2,*] :count contacts may already have this message — we could not confirm '
            .'them, so we did not send again',
            $total,
            ['count' => number_format($total)],
        );

        app(ActivityService::class)->record(
            AutopilotActionType::OwnerActionNeeded,
            $this->locationId,
            [
                'campaign_id' => $campaign->getKey(),
                'unconfirmed' => $total,
            ],
            $title,
        );
    }

    /**
     * Tell the owner who this campaign could not reach, and why.
     *
     * ⛔ **THIS IS 2687'S DELIVERABLE. THE CEILING IS ONLY WHAT MAKES IT
     * POSSIBLE.** *"'5,000 contacts could not be sent to: no state on file' is a
     * thing somebody fixes; a queue quietly retrying forever is not."* A ceiling
     * on its own converts a campaign that never finishes into one that finishes
     * having sent nothing, which is not obviously an improvement — the sentence
     * is what makes it one.
     *
     * ⚠️ **AT CLOSE, AND ONCE.** A campaign only closes when nothing is
     * outstanding, so this is the single moment the whole picture exists. Per
     * pass it would report a batch, and an owner told about eleven contacts
     * fourteen times learns nothing and stops reading.
     *
     * ⚠️ **THE BIGGEST CAUSE, NAMED — NOT A LIST OF ALL OF THEM.** Grouping by
     * reason and printing every group is a report; `22`'s rule is that a string
     * names what the person controls, and there is one action to take. The
     * remainder is counted into the sentence so the arithmetic still adds up.
     *
     * ⚠️ **NOTHING PERSONAL, WHICH IS `AutopilotJob::activityMetadata()`'s
     * standing rule.** `SendRefusalReason::ownerSentence()` names a rule and
     * never a person, the count is a count, and no identifier goes near this.
     *
     * ⚠️ **`ownerSentence(OutreachChannel::Sms)`, NAMED EXPLICITLY — 10240,
     * PHASE 2.** This campaign runner never sends anything but SMS (`decide()`,
     * `refusalFor()` and the arbiter above are all asked with
     * `OutreachChannel::Sms`), so the sentence is always correct here; the
     * parameter exists because {@see SendRefusalReason::ownerSentence()}
     * is shared with the review-invite path, which is not SMS-only.
     *
     * ⚠️ **SILENT WHEN EVERYBODY WAS REACHED.** A feed item saying nothing went
     * wrong is the thing that makes a feed not worth reading, which
     * `AutopilotJob::activityAction()` argues at length one class up.
     */
    private function reportWhoCouldNotBeReached(Campaign $campaign): void
    {
        // ⚠️ Counted in the database rather than hydrated: a campaign's refused
        // set is five thousand rows in the case this ruling is named after, and
        // the only thing wanted from them is a tally.
        $byReason = [];

        foreach (CampaignRecipient::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('status', CampaignRecipientStatus::Refused)
            ->whereNotNull('refusal_reason')
            ->groupBy('refusal_reason')
            ->selectRaw('refusal_reason, count(*) as total')
            ->toBase()
            ->get() as $row) {
            $byReason[(string) $row->refusal_reason] = (int) $row->total;
        }

        $total = array_sum($byReason);

        if ($total === 0) {
            return;
        }

        arsort($byReason);

        $reason = SendRefusalReason::from((string) array_key_first($byReason));
        $leading = $byReason[$reason->value];

        $title = trans_choice(
            '{1} 1 contact could not be sent to: :why|[2,*] :count contacts could not be sent to: :why',
            $total,
            ['count' => number_format($total), 'why' => $reason->ownerSentence(OutreachChannel::Sms)],
        );

        if ($leading < $total) {
            // The honest qualifier. Naming one cause for a mixed set without it
            // would be a true sentence that reads as a complete one.
            $title .= ' (and '.number_format($total - $leading).' for other reasons)';
        }

        app(ActivityService::class)->record(
            AutopilotActionType::OwnerActionNeeded,
            $this->locationId,
            [
                'campaign_id' => $campaign->getKey(),
                'unreachable' => $total,
                'leading_reason' => $reason->value,
            ],
            $title,
        );
    }
}
