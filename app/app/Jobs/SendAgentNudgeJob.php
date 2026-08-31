<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MessageSender;
use App\Enums\AgentThreadStatus;
use App\Enums\AssistantToggle;
use App\Enums\AutopilotActionType;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\SendRefusalReason;
use App\Models\AgentNudge;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Agent\AgentNudges;
use App\Services\Assistant\AssistantToggles;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SendPermit;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendKey;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * Send one silent thread its single follow-up — T176 §2.2 skill 14, patch P11.
 *
 * ## ⛔ WHAT CLASS OF MESSAGE THIS IS, ARGUED RATHER THAN ASSUMED
 *
 * **`OutreachPurpose::Transactional`, with the daytime window applied on top of
 * it.** Those two look contradictory and are not:
 *
 *  - **Transactional** is the honest classification. This is one sentence inside
 *    a conversation the customer opened, about a link they asked for, offering
 *    nothing. `AnswerAgentTurnJob` classifies the reply that carried the link
 *    the same way, and calling the follow-up `Marketing` would say the second
 *    half of one exchange is a different kind of thing from the first.
 *    ⚠️ **It is not CAN-SPAM's problem at all** — CAN-SPAM governs commercial
 *    *email*, and this is SMS. `59ce83c`'s one-click unsubscribe and footer are
 *    the email path's answer and have no place here; STOP and HELP are the SMS
 *    equivalents, and they are unconditional and platform-side on every message.
 *  - **And the daytime window anyway**, because §2.2 says so in as many words:
 *    *"quiet-hours-respecting (not time-critical)"*. `Transactional` normally
 *    skips it — `ConsentService::stateRefusal()` returns before any window for a
 *    purpose outside `isSubjectToDoNotCall()`, which is the T69 law and is
 *    correct for a *reply*. **A nudge is not a reply**: it is chosen by a sweep,
 *    on our clock, about a message we sent four hours ago, which is exactly the
 *    distinction `ReviewInviteSender::remind()` drew for the invite follow-up
 *    (T176 P14, `f8c7709`). So it asks `daytimeWindowRefusal()` explicitly — the
 *    same one implementation, with the purpose gate lifted off.
 *
 * ⚠️ **A CLOSED WINDOW IS A HOLD, NOT A CANCEL.** The sweep asks again on its
 * next tick and `expires_at` is what eventually gives up. That is what makes a
 * hold affordable here, and it is `remind()`'s argument again.
 *
 * ## ⛔ AND IT DOES NOT RIDE THE SEND-COLLISION ARBITER, WHICH IS A DECISION
 *
 * `MarketingTouch` has four cases and **no case for a service message**, by
 * design — its own docblock: *"a missed-call text-back and a reply in a
 * conversation are never held, by anything, at any hour, so they are not a
 * low-priority case here, they never ask the arbiter at all."* Classifying this
 * as one of the four in order to get it arbitrated would file a service
 * follow-up as marketing and move the priority ladder for everybody else's
 * sends. The containment that does apply is the one that matters at volume:
 * **one per thread, ever**, held by a unique index rather than by a rate.
 *
 * ## The gates, all of them
 *
 * `AutopilotJob` brings our kill switch, the tenant's pause, a compliance
 * suspension, the run row, the idempotency claim and the backoff. On top of
 * those this job asks: the nudge is still pending · the window has not closed ·
 * the tenant has not switched skill 14 off since it was armed · the thread is
 * still one the assistant may speak on · nothing has been answered on it · the
 * daytime window is open · a permit. Every one of them has its own test.
 *
 * ⛔ **NO SEND PATH OF ITS OWN**, on `AnswerAgentTurnJob`'s reasoning: 2970–2979
 * records a platform halt that stopped one sender out of two, because that
 * sender had built a path of its own.
 *
 * ⛔ **AND NO MODEL CALL.** The follow-up is one fixed sentence, so there is no
 * AI spend to debit, no rail-6 fallback to write and no prompt to fence. A model
 * asked to write *"are you still there"* is a vendor dependency, a cost and an
 * output lint on the least valuable message the assistant sends.
 */
final class SendAgentNudgeJob extends AutopilotJob
{
    /**
     * The whole message.
     *
     * ⛔ **A CONSTANT RATHER THAN A REGISTRY KEY**, on
     * `AgentComposer::MODEL_DOWN_TEMPLATE`'s reasoning: it is the one thing a
     * customer reads when *nothing else has happened*, and an operator-editable
     * version is a screen from which a compliant message can be made
     * non-compliant with no review. It names the business, offers nothing, and
     * asks a question the recipient can ignore.
     */
    private const string TEMPLATE = 'Just checking you got that from %s — happy to help if you have any questions.';

    private bool $sent = false;

    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $nudgeId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'agent.nudge';
    }

    /**
     * ⚠️ **KEYED ON THE NUDGE, WHICH IS KEYED ON THE THREAD.** The row is unique
     * per conversation, so this is the strongest key available and — unlike
     * `AnswerAgentTurnJob`'s first attempt, which keyed on a turn count the job
     * itself advanced (4139) — it does not move under the job's own feet.
     */
    protected function idempotencyKey(): string
    {
        return 'agent-nudge:'.$this->nudgeId;
    }

    /**
     * ⚠️ **FALSE UNTIL A MESSAGE ACTUALLY LEFT, ON `SendInviteReminderJob`'s
     * REASONING AND FOR ITS REASON.** Nearly every refusal on this path is
     * temporary — a closed daytime window, a platform halt, a tenant pause, an
     * exhausted balance — and every one of them clears by some later sweep.
     * Keeping the claim would silently turn each into *"this thread never gets
     * its follow-up"*, and `expires_at` is what is supposed to make that
     * decision, visibly, with a reason written on the row.
     */
    protected function claimIsSpent(): bool
    {
        return $this->sent;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->follow();
    }

    /**
     * ⛔ **THE SAME WORK, AND RULE 44 DEMONSTRATED RATHER THAN EXCEPTED.**
     * Nothing on this path touches the Google Business Profile API — the message
     * is our own texter and there is no link to mint — so there is no
     * reduced-capability version to describe. `SendInviteReminderJob` makes the
     * identical argument.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->follow();
    }

    /**
     * @return array<string, mixed>
     */
    private function follow(): array
    {
        $nudges = app(AgentNudges::class);

        $nudge = AgentNudge::query()->find($this->nudgeId);

        if (! $nudge instanceof AgentNudge) {
            return ['sent' => false, 'reason' => 'nudge_not_found'];
        }

        if (! $nudge->isPending()) {
            // A redelivery, or two sweeps overlapping. The row is the receipt.
            return ['sent' => false, 'reason' => 'already_settled'];
        }

        // ⛔ **THE WINDOW IS CHECKED FIRST, AND AN EXPIRED NUDGE IS CANCELLED
        // RATHER THAN SKIPPED.** A row left pending for ever reads on an
        // operator's screen as work in progress.
        if (Carbon::now()->greaterThan($nudge->expires_at)) {
            $nudges->cancel($nudge, 'window_closed');

            return ['sent' => false, 'reason' => 'window_closed'];
        }

        // ⚠️ **ASKED AGAIN HERE, HOURS AFTER ARMING.** `AgentNudges::arm()` asks
        // it too; this is the case that one cannot cover — a business that
        // switched skill 14 off while the follow-up was waiting.
        if (! app(AssistantToggles::class)->isEnabled(AssistantToggle::Nudge)) {
            $nudges->cancel($nudge, 'switched_off');

            return ['sent' => false, 'reason' => 'switched_off'];
        }

        $conversation = Conversation::query()->find($nudge->conversation_id);

        if (! $conversation instanceof Conversation) {
            $nudges->cancel($nudge, 'thread_gone');

            return ['sent' => false, 'reason' => 'thread_gone'];
        }

        // ⛔ **RAIL 4 REACHES THIS JOB TOO, AND THAT IS WHY THE CHECK IS HERE
        // RATHER THAN ONLY IN THE SWEEP.** *"The moment a human replies the agent
        // goes silent on that thread. No exceptions."* A follow-up signed by the
        // assistant landing after the owner picked the thread up is exactly the
        // exception that sentence forbids — and the same holds for a thread that
        // escalated, capped, or closed.
        if (! $conversation->agent_status->agentMaySpeak()) {
            $nudges->cancel($nudge, $this->silenceReason($conversation->agent_status));

            return ['sent' => false, 'reason' => 'agent_may_not_speak'];
        }

        if ($nudges->wasAnswered($nudge, $conversation)) {
            $nudges->cancel($nudge, 'customer_replied');

            return ['sent' => false, 'reason' => 'customer_replied'];
        }

        $customer = Customer::query()->find($nudge->customer_id);

        if (! $customer instanceof Customer) {
            $nudges->cancel($nudge, 'contact_gone');

            return ['sent' => false, 'reason' => 'contact_gone'];
        }

        // ⛔ **§2.2's "QUIET-HOURS-RESPECTING" — see the class docblock for why a
        // `Transactional` message asks this at all.** A hold, never a cancel: the
        // next sweep asks again and `expires_at` is what gives up.
        $windowRefusal = app(ConsentService::class)->daytimeWindowRefusal($customer, null);

        if ($windowRefusal instanceof SendRefusalReason) {
            return ['sent' => false, 'reason' => $windowRefusal->value, 'held' => true];
        }

        $decision = app(ConsentService::class)->decide(
            $customer,
            OutreachChannel::Sms,
            // See the class docblock. A test mutates this to `Marketing`.
            OutreachPurpose::Transactional,
        );

        $permit = $decision->permit;

        if (! $permit instanceof SendPermit) {
            // ⚠️ **NOT CANCELLED.** Consent can be recorded, a suppression lifted
            // and a state filled in; every one of those makes a later sweep the
            // right answer, and `expires_at` is the honest place to give up.
            return [
                'sent' => false,
                'reason' => ($decision->reason ?? SendRefusalReason::NoConsentRecord)->value,
                'held' => true,
            ];
        }

        $outcome = app(MessageSender::class)->send(OutboundMessage::for(
            permit: $permit,
            body: sprintf(self::TEMPLATE, $this->businessName()),
            // Keyed on the nudge, so a redelivery meets `outreach_messages`'
            // unique index and the sender answers `Duplicate`, debiting nothing.
            key: SendKey::for($permit, 'agent-nudge:'.$nudge->getKey()),
            purpose: OutreachPurpose::Transactional,
        ));

        if (! $outcome->wasSent()) {
            // A refusal downstream of the permit — the platform halt, the tenant
            // pause, the complaint-rate trip, no sending number, an exhausted
            // balance. All temporary, so the row stays pending.
            return [
                'sent' => false,
                'reason' => $outcome->reason?->value,
                'status' => $outcome->status->value,
                'held' => true,
            ];
        }

        $nudges->markSent($nudge);
        $this->sent = true;

        return [
            'sent' => true,
            'send_key' => $outcome->key->value,
            'status' => $outcome->status->value,
        ];
    }

    /**
     * Why the assistant went quiet, in the vocabulary the row stores.
     */
    private function silenceReason(AgentThreadStatus $status): string
    {
        return match ($status) {
            AgentThreadStatus::HumanTakeover => 'human_took_over',
            AgentThreadStatus::Escalated => 'escalated',
            AgentThreadStatus::TurnCapped => 'turn_capped',
            AgentThreadStatus::Closed => 'thread_closed',
            AgentThreadStatus::Unhandled, AgentThreadStatus::AgentHandling => 'agent_may_not_speak',
        };
    }

    private function businessName(): string
    {
        $name = trim((string) (Business::query()->find(Tenancy::idOrFail())->name ?? ''));

        return $name === '' ? 'us' : $name;
    }

    /**
     * ⚠️ **ONE FEED ITEM, AND ONLY WHEN SOMETHING WENT.** `AutopilotJob` names
     * *"checked something and found nothing"* as the automation that makes the
     * feed worse, and a held or cancelled nudge is exactly that.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->sent ? AutopilotActionType::AssistantFollowedUpOnce : null;
    }

    /**
     * ⛔ **THE NUDGE, NEVER THE RECIPIENT.** An address or a number on a run row
     * would put personal data in a table nobody thinks of as holding it (627).
     *
     * @return array<string, mixed>
     */
    protected function activityMetadata(): array
    {
        return ['nudge_id' => $this->nudgeId];
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return parent::input() + ['nudge_id' => $this->nudgeId];
    }
}
