<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Enums\AssistantToggle;
use App\Jobs\SendAgentNudgeJob;
use App\Models\AgentNudge;
use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Assistant\AssistantToggles;
use App\Services\Config\DefaultsRegistry;
use App\Services\Links\TenantLinks;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Skill 14, the silent-customer nudge — T176 §2.2 row 14, patch P11.
 *
 * *"Quote/fee/booking link sent, no reply → ONE polite nudge inside 24h,
 * quiet-hours-respecting (not time-critical), then stop; tenant toggle."*
 *
 * ## ⛔ THE TRIGGER IS A LINK GOING OUT, AND {@see TenantLinks::shortLinkFor()}
 * IS THE ONE PLACE THAT HAPPENS
 *
 * §2.2's trigger column names three things — a quote, a fee, a booking link —
 * and all three leave through `shortLinkFor()`, which is R14's chokepoint and
 * already takes the conversation. Arming there rather than in the job that sends
 * the turn is what keeps the trigger honest: a nudge fires because a link went
 * out, not because a thread went quiet.
 *
 * ⛔ **AND THE REVIEW-ASK LINK IS STRUCTURALLY EXCLUDED, WHICH IS DELIBERATE.**
 * P12's offer is minted through `ShortLinks::mint()` by {@see ReviewAskBridge},
 * not through `shortLinkFor()`, so it never reaches this class. Nudging somebody
 * who did not answer a review invitation would be a *second* solicitation to
 * review, which is not what §2.2 asks for and not something this product does.
 *
 * ⛔ **THIS PARAGRAPH SAID `shortLinkFor()` HAD NO CALLER, AND THAT STOPPED BEING
 * TRUE AT P6** (4545). It read *"the assistant's link-sending half is unbuilt —
 * `AgentComposer` puts no tenant link in the prompt — so nothing arms a nudge in
 * production yet"*, and `AgentComposer::facts()` has called `shortLinkFor()`
 * since 4271. CLAUDE.md 2505's shape, in the file whose own subject is 272's.
 *
 * ## ⛔ AND SKILL 14 STILL CANNOT FIRE, FOR TWO SEPARATE REASONS (4546)
 *
 * Both were found by the adversarial pass over the composed tree, and **neither
 * is fixed here** — the ruling is at the end of this block.
 *
 *  1. **The baseline is one turn behind.** {@see self::arm()} stores
 *     `armed_at_turns = agent_turns_used`, and arming happens *inside* a turn —
 *     `AnswerAgentTurnJob` calls the composer, which mints the link, and calls
 *     `recordTurn()` afterwards. The counter is therefore always one ahead by the
 *     time anything reads it, {@see self::wasAnswered()} is true the instant the
 *     row exists, and `SendAgentNudgeJob` cancels every nudge with
 *     `customer_replied` for a customer who has said nothing. **Confirmed by
 *     execution rather than by reading.**
 *  2. **The trigger no longer means what it says.** The paragraph above chose
 *     this arming point to guarantee *"a nudge fires because a link went out"* —
 *     and since P6 the composer mints a booking short link on **every turn where
 *     skill 5 is lit**, whether or not the model quotes it and whether or not the
 *     message sends. Arming now means *"this tenant has a booking URL"*.
 *
 * ⛔ **FIXING ONLY (1) WOULD BE WORSE THAN THE DEFECT, WHICH IS WHY NEITHER IS
 * FIXED IN THE DISPATCHER'S SLICE** (4546). The one-line `+ 1` is right on its
 * own terms and, with (2) standing, it converts a feature that texts nobody into
 * one that texts every quiet contact of every tenant holding a booking URL —
 * about a link they were never sent. The honest repair is P12's shape, which is
 * already in `AnswerAgentTurnJob` for the review ask: arm **after the send**, and
 * only when the body actually carried the link. That moves the chokepoint out of
 * `shortLinkFor()` and needs `AgentReplyDraft` to carry which link the prompt
 * offered — a redesign across P4, P6, P11 and P12 rather than a line, and not one
 * to ride in the same commit as a compliance gate.
 *
 * ⚠️ **UNTIL THEN THE FEATURE IS INERT AND THAT IS THE SAFE DIRECTION**, which is
 * the whole reason the half-fix is refused: a nudge that never sends costs a
 * follow-up nobody received, and a nudge that sends unearned is a text to a
 * member of the public with no trigger behind it.
 *
 * ## ⚠️ "NO REPLY" IS A PROXY, AND THE PROXY IS NAMED
 *
 * See {@see self::wasAnswered()}. This schema has no observable *"the customer
 * replied"*, so the turn counter stands in for one.
 *
 * ## ⛔ THIS IS NOT THE SEND PERMIT
 *
 * A row here is a schedule. {@see SendAgentNudgeJob} asks
 * `ConsentService` for the permit and sends through `MessageSender`, so the kill
 * switch, the tenant pause, the complaint-rate trip, suppression, STOP and the
 * Do Not Call registers all apply without this class knowing they exist.
 */
final class AgentNudges
{
    /**
     * The outside edge of §2.2's own sentence — *"inside 24h"*.
     *
     * ⛔ **A CONSTANT RATHER THAN A REGISTRY KEY, AND THE ASYMMETRY WITH THE
     * DELAY IS THE POINT.** *When* to follow up is a judgement an operator may
     * reasonably tune; *whether a two-day-old follow-up is still a follow-up* is
     * not — a nudge that arrives after the customer has forgotten the
     * conversation is a cold text about something they no longer recognise.
     * Making this editable would offer an operator the ability to turn skill 14
     * into exactly that, from a screen with no context.
     */
    public const int WINDOW_HOURS = 24;

    public function windowHours(): int
    {
        return $this->registry->int('agent.nudges.window_hours');
    }

    /**
     * Refused below this, for `AgentThreadStates::MINIMUM_TURN_CAP`'s reason: a
     * delay of zero would text somebody the instant after the link, which reads
     * to the recipient as a malfunction rather than a courtesy.
     */
    public const int MINIMUM_DELAY_MINUTES = 5;

    public function __construct(
        private readonly DefaultsRegistry $registry,
        private readonly AssistantToggles $toggles,
    ) {}

    /**
     * Owe this thread one follow-up, if it is not owed one already.
     *
     * ⚠️ **IDEMPOTENT, AND THE FIRST ARMING IS THE ONE THAT STANDS.** Three links
     * in one message is one silence, not three; and the clock a customer
     * experiences starts at the first one. Re-arming on the second would push the
     * follow-up later every time the assistant was helpful.
     *
     * ⛔ **THE TOGGLE IS ASKED HERE *AND* AT THE SEND.** Here, so a business that
     * switched the nudge off never accumulates a schedule it will not act on;
     * there, because a business may switch it off during the four hours in
     * between, and that is the case the second check exists for. Neither is
     * redundant and both have a test.
     */
    public function arm(Conversation $conversation): ?AgentNudge
    {
        $businessId = Tenancy::idOrFail();

        if ((int) $conversation->business_id !== $businessId) {
            // A hydrated thread carried across a tenant boundary — the one leak
            // neither the global scope nor RLS can see.
            return null;
        }

        if (! $this->toggles->isEnabled(AssistantToggle::Nudge)) {
            return null;
        }

        $customerId = $conversation->customer_id;

        if ($customerId === null) {
            // Nobody to follow up with. A first-time caller who is not a contact
            // yet is the ordinary case, and manufacturing one in order to text
            // them is the refusal `AnswerAgentTurnJob` already makes.
            return null;
        }

        if (! Customer::query()->whereKey($customerId)->exists()) {
            return null;
        }

        // ⚠️ **THIS CHECK IS AN OPTIMISATION AND NOT THE GUARANTEE, AND THREE
        // OBSERVED MUTATION RUNS ARE WHY THAT IS STATED RATHER THAN ASSUMED.**
        // Deleting this `exists()` leaves the suite green, because the unique
        // index on `agent_nudges.conversation_id` refuses the insert and the
        // `catch` below returns the same `null`. Dropping the index instead also
        // leaves it green, because this line refuses first. **Only removing both
        // reddens** *a second link on the same thread does not move or duplicate
        // the follow-up*.
        //
        // ⛔ **THE INDEX IS THE ONE THAT HOLDS**, because this is a
        // check-then-act and two workers can pass it together. What this line
        // buys is that the ordinary second link costs a read rather than a
        // failed INSERT inside a savepoint. Written down rather than dressed up
        // as a safety check (314–316), so the next reader knows which to keep.
        if (AgentNudge::query()->where('conversation_id', $conversation->getKey())->exists()) {
            return null;
        }

        $armedAt = Carbon::now();

        try {
            // ⛔ **A SAVEPOINT, BECAUSE A UNIQUE VIOLATION ABORTS THE ENCLOSING
            // POSTGRES TRANSACTION** and this is called from inside a send. The
            // same lesson `ReviewAskBridge::record()` records.
            return DB::transaction(fn (): AgentNudge => AgentNudge::query()->create([
                'location_id' => $conversation->location_id,
                'conversation_id' => (int) $conversation->getKey(),
                'customer_id' => $customerId,
                'armed_at' => $armedAt,
                'armed_at_turns' => max(0, $conversation->agent_turns_used),
                'due_at' => $armedAt->copy()->addMinutes($this->delayMinutes()),
                'expires_at' => $armedAt->copy()->addHours($this->windowHours()),
            ]));
        } catch (Throwable) {
            // The loser of a race. The winner owes the same follow-up.
            return null;
        }
    }

    /**
     * Stop owing it, and say why.
     *
     * ⚠️ **A NO-OP ON A NUDGE THAT HAS ALREADY GONE OR ALREADY STOPPED**, rather
     * than an exception: every caller is a job or a sweep, and a second cancel is
     * a redelivery rather than a caller error.
     */
    public function cancel(AgentNudge $nudge, string $reason): AgentNudge
    {
        Tenancy::idOrFail();

        if (! $nudge->isPending()) {
            return $nudge;
        }

        $nudge->forceFill([
            'cancelled_at' => Carbon::now(),
            'cancel_reason' => $reason,
        ])->save();

        return $nudge;
    }

    /**
     * Mark it sent. The row is the receipt as well as the schedule.
     */
    public function markSent(AgentNudge $nudge): AgentNudge
    {
        Tenancy::idOrFail();

        $nudge->forceFill(['sent_at' => Carbon::now()])->save();

        return $nudge;
    }

    /**
     * Whether the customer has said something since the link went out.
     *
     * ⛔ **THIS IS A PROXY AND IT IS NAMED AS ONE.** There is no observable *"the
     * customer replied"* in this schema:
     *
     *  - ⛔ **THIS SAID `messages` HAD "NO WRITER ANYWHERE IN `app/`" AND P18
     *    LANDED ONE** (4545) — `ConversationThreads::record()`. So the gap named
     *    two paragraphs down *has* closed and this proxy could now be replaced by
     *    reading the thread's own inbound messages. It is **not** replaced here,
     *    because the proxy is not what stops skill 14 firing (see the class
     *    docblock) and changing the definition of "answered" in the same slice
     *    would make that measurement unreadable;
     *  - `inbound_messages` is the platform-scoped STOP/HELP keyword register. It
     *    has no `business_id`, no body and a hashed identifier, so it cannot
     *    answer *"did this contact of this tenant reply"* and was never meant to.
     *
     * What is observable is `conversations.agent_turns_used`, moved by
     * `AgentThreadStates::recordTurn()` whenever the assistant answers an inbound
     * — so a counter that has moved since arming means something arrived and was
     * answered.
     *
     * ⚠️ **THE GAP IS A REPLY THE ASSISTANT DID NOT ANSWER**: one that arrived
     * while the tenant was paused, or while our kill switch was thrown. The
     * counter would not have moved and the nudge would still go. That is a
     * message reading *"still there if you need us"* to somebody who wrote to us
     * an hour ago — mildly wrong rather than harmful. ⚠️ **It said this "goes
     * away entirely when P18 gives `messages` a writer", and P18 did** (4545):
     * the remedy is now available and is deliberately not taken in the
     * dispatcher's slice. Every other silence — a person taking
     * the thread over, an escalation, a cap, a close — is caught by
     * {@see SendAgentNudgeJob}'s status check instead.
     */
    public function wasAnswered(AgentNudge $nudge, Conversation $conversation): bool
    {
        return $conversation->agent_turns_used > $nudge->armed_at_turns;
    }

    /**
     * Everything this tenant owes right now, oldest first.
     *
     * @return Collection<int, AgentNudge>
     */
    public function due(?Carbon $now = null): Collection
    {
        Tenancy::idOrFail();

        $now ??= Carbon::now();

        return AgentNudge::query()
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->where('due_at', '<=', $now)
            // ⚠️ **EXPIRED ROWS ARE STILL RETURNED, DELIBERATELY.** The sweep
            // cancels them with a reason rather than skipping them, so a thread
            // that was never followed up says so on its own row instead of
            // sitting pending for ever and reading as work in progress.
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * How long after the link the follow-up is owed.
     *
     * ⚠️ **CLAMPED AT BOTH ENDS, AND THE UPPER CLAMP IS §2.2's SENTENCE.** A
     * delay past the window would arm a nudge that is expired before it is due —
     * a schedule that can never fire, which reads on every screen as a feature
     * that is simply not working.
     */
    private function delayMinutes(): int
    {
        $configured = $this->registry->int('assistant.nudge_delay_minutes');

        return max(
            self::MINIMUM_DELAY_MINUTES,
            min($configured, $this->windowHours() * 60 - self::MINIMUM_DELAY_MINUTES),
        );
    }
}
