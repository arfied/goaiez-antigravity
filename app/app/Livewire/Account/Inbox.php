<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Contracts\Agent\AgentThreads;
use App\Enums\OutreachChannel;
use App\Enums\SendRefusalReason;
use App\Jobs\SummariseClosedThreadJob;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Conversations\ConversationThreads;
use App\Services\Conversations\InboxReplies;
use App\Services\Messaging\Outbound\SendOutcome;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * The Inbox — text messages with the people who contacted the business (R21).
 *
 * The whole surface on one screen: the threads, one conversation, a box to
 * answer it, and the two controls that decide whether the assistant is speaking.
 * Threads open in place rather than at their own URL — a conversation is one
 * contact rather than a destination (`Support`'s distinction, decision 396's
 * rule that no id travels in a URL), and a second route would be a second thing
 * to authorise for no gain.
 *
 * ⚠️ **NOTHING HERE NAMES A TABLE.** {@see ConversationThreads} is the only
 * reader and writer of the two, and {@see AgentThreads} the only reader and
 * writer of the five `agent_*` columns — so the tenant boundary on every read is
 * the global scope plus RLS, in one place, rather than a `where` this component
 * could forget.
 *
 * ⚠️ **A THREAD ID ARRIVING FROM THE BROWSER IS NOT TRUSTED AND DOES NOT NEED TO
 * BE.** A Livewire action is callable whatever rendered it (391), so `open(9)`
 * for another tenant's thread is one line to attempt — and it finds nothing,
 * because every read runs inside this tenant's own tenancy. The test drives that
 * directly rather than asserting a button is absent.
 *
 * ## The latch is rendered honestly, and that is the load-bearing part
 *
 * ⛔ **NO STATE IS EVER INFERRED, AND "THE ASSISTANT IS ANSWERING" IS NEVER THE
 * DEFAULT.** P3's deleted stub carried the reason: a thread state with a false
 * latch and zero turns would let this screen say *"your assistant is
 * answering"* while nothing was. Every label comes from
 * `AgentThreadStatus::ownerLabel()`, which is written on the column by
 * `AgentThreadStates` and by nothing else, and a thread this application opens
 * starts `Unhandled` — *"waiting for a first reply"*, which is true.
 *
 * ✅ **AND EVERY STATE THIS SCREEN RENDERS IS NOW REACHABLE** (4540). This
 * paragraph said *"nothing dispatches `GroundAgentTurnJob` anywhere in `app/`
 * today, so `AgentHandling` and `TurnCapped` cannot arise from the agent yet"* —
 * honest at the time and no longer true: `AgentTurns` dispatches a turn from the
 * carrier webhook, so `AgentHandling`, `TurnCapped` and `Escalated` all arise
 * from the assistant itself, and {@see self::finish()} is what produces
 * `Closed`. The five-state law is now a description of live states rather than
 * of an enum.
 *
 * ## Replying is a send
 *
 * ⛔ **IT GOES THROUGH {@see InboxReplies} AND THEREFORE THROUGH
 * `MessageSender`** — consent, suppression, STOP, the registers, quiet hours,
 * the per-tenant pause, the global halt, the credit debit and the
 * `outreach_messages` row. There is no second send path on this screen and there
 * may never be one.
 *
 * ⚠️ **A REAL `GET` IS REQUIRED TO PROVE THE SHELL RENDERS.**
 * `Livewire::test()` never renders the layout (570) and runs no middleware
 * (809), so a component test proves the component and nothing about the route.
 */
#[Layout('components.account.layout')]
final class Inbox extends Component
{
    /**
     * ⚠️ **`#[Locked]`, BECAUSE IT IS AN IDEMPOTENCY KEY.** A browser that could
     * set this could make a retry compute a *different* `SendKey` and send the
     * same message twice — the one thing the key exists to stop. It is minted
     * here and rotated only after a send is accepted.
     */
    #[Locked]
    public string $draftKey = '';

    public ?int $openThreadId = null;

    public string $reply = '';

    public function mount(): void
    {
        abort_if(Tenancy::id() === null, 403);

        $this->draftKey = (string) Str::uuid();
    }

    /**
     * ⚠️ **THE ID IS RESOLVED HERE RATHER THAN ONLY AT RENDER.** A foreign or
     * vanished id would otherwise set the property, find nothing at render and
     * quietly show the list again — which reads to the person who clicked as a
     * screen that ignored them, and to a reviewer as an unproven refusal. 404
     * is the same answer for both cases deliberately: telling them apart would
     * confirm that a row exists in a tenancy the reader has no business knowing
     * about.
     */
    public function bodyLimit(): int
    {
        return app(InboxReplies::class)->bodyLimit();
    }

    public function open(int $threadId, ConversationThreads $store): void
    {
        abort_if(Tenancy::id() === null, 403);
        abort_if($store->find($threadId) === null, 404);

        $this->openThreadId = $threadId;
        $this->reply = '';
        $this->draftKey = (string) Str::uuid();
        $this->resetErrorBag();
    }

    public function back(): void
    {
        $this->reset(['openThreadId', 'reply']);
        $this->resetErrorBag();
    }

    /**
     * Answer the open thread.
     *
     * ⚠️ **THE VALIDATION IS HERE RATHER THAN IN A FORM REQUEST**, which is the
     * convention every owner screen in this application already follows
     * (`Support`, `ImportCustomers`, `Knowledge`): a Livewire action is not an
     * HTTP request and has no request object to validate. `InboxReplies`
     * re-checks both bounds and throws, so the rule is not only on the screen.
     */
    public function send(InboxReplies $replies, ConversationThreads $store): void
    {
        $thread = $this->requireOpenThread($store);

        if ($thread === null) {
            return;
        }

        $this->validate([
            'reply' => ['required', 'string', 'min:1', 'max:'.$this->bodyLimit()],
        ]);

        $result = $replies->send($thread, $this->reply, $this->user(), $this->draftKey);

        if ($result instanceof SendRefusalReason) {
            // ⚠️ **THE REASON, NOT "SOMETHING WENT WRONG".**
            // `ownerSentence()` names a rule or a state and never a contact
            // detail, so it is safe on the screen and answers the question the
            // owner is about to raise a ticket about.
            //
            // ⚠️ **`OutreachChannel::Sms`, NAMED EXPLICITLY — 10240, PHASE 2.**
            // `InboxReplies::send()` decides consent on `OutreachChannel::Sms`
            // alone (this is the Inbox's own text-message thread), so the
            // sentence is always correct here; the parameter exists because
            // `ownerSentence()` is shared with the review-invite path, which
            // is not SMS-only.
            $this->addError('reply', 'Not sent — '.$result->ownerSentence(OutreachChannel::Sms).'.');

            return;
        }

        if (! $result->wasSent()) {
            $this->addError('reply', 'Not sent — '.$this->explain($result).'.');

            return;
        }

        $this->reply = '';

        // ⚠️ **THE KEY ROTATES ONLY ON AN ACCEPTED SEND.** A refusal keeps it,
        // so pressing send again after fixing nothing cannot become a second
        // charge for the same message.
        $this->draftKey = (string) Str::uuid();

        // ⚠️ **NO NAME, NO NUMBER, NO MESSAGE TEXT IN THE TOAST** (decision
        // 104). The toast is never the audit record; the audit row is in
        // `audit_log` and the message is on the screen behind it. It also says
        // what changed about the assistant, because that is the half an owner
        // would otherwise not notice.
        Toaster::success('Sent. Your assistant will stay quiet on this conversation.');
    }

    /**
     * Take the conversation over without answering it yet (rail 4).
     *
     * ⚠️ **A CONTROL AS WELL AS A CONSEQUENCE.** Sending latches, but an owner
     * who has read a thread and wants to answer it by phone needs the assistant
     * silent *before* they do — and the alternative, typing a placeholder text
     * to trigger the latch, would send a customer a message that means nothing.
     */
    public function handOver(AgentThreads $threads, ConversationThreads $store): void
    {
        $thread = $this->requireOpenThread($store);

        if ($thread === null) {
            return;
        }

        $threads->latch($thread, $this->user());

        Toaster::success('You are answering this one now.');
    }

    /**
     * Finish the conversation — rail 9's *resolved*, and 4543.
     *
     * ⛔ **THIS IS THE CALLER `AgentThreadStates::close()` SHIPPED WITHOUT AND
     * NAMED IN ADVANCE**: *"nothing resolves a thread today — that is P18's
     * control."* P18 built the screen and not the control, so `close()` had no
     * caller anywhere in `app/`, and with it neither did rail 9's *resolved*
     * summary — {@see SummariseClosedThreadJob} was reachable only from
     * the turn cap. Decision 272's shape wearing a button that was never drawn.
     *
     * ⛔ **CLOSING IS ONE-WAY AND THE SCREEN SAYS SO RATHER THAN ASKING.** The
     * service refuses to re-arm a closed thread — *"reopening a resolved
     * conversation is a new thread, so that the close summary already sent to the
     * owner stays true"* — so the copy names the consequence and the control is
     * hidden afterwards. A confirmation dialog was refused: it is a support
     * surface for a state the customer's next text recreates anyway.
     *
     * ⚠️ **IDEMPOTENT AT THE SERVICE, SO A DOUBLE SUBMIT IS NOT AN ERROR** and,
     * more usefully, sends no second owner summary. The toast is written to be
     * true of both outcomes.
     *
     * ⛔ **IT IS NOT A SEND AND IT TELLS THE CUSTOMER NOTHING.** Nobody at the
     * other end learns a business pressed this; closing is bookkeeping about who
     * is answering, and manufacturing a goodbye text would be a message to a
     * member of the public that no rule asked for.
     */
    public function finish(AgentThreads $threads, ConversationThreads $store): void
    {
        $thread = $this->requireOpenThread($store);

        if ($thread === null) {
            return;
        }

        $threads->close($thread, $this->user());

        // ⚠️ **NO NAME, NO NUMBER, NO MESSAGE TEXT** (decision 104), and outcome
        // language: it names what the person did, not what the state machine did.
        Toaster::success('Marked as finished.');
    }

    /**
     * Hand the conversation back to the assistant.
     *
     * ⚠️ **IDEMPOTENT AT THE SERVICE, SO A STALE SCREEN IS NOT AN ERROR.**
     * `rearm()` no-ops rather than throwing on a thread that cannot be
     * re-armed, *"because throwing would turn a second click into a 500 on a
     * compliance-shaped path"*. The toast is therefore written to be true of
     * both outcomes.
     */
    public function handBack(AgentThreads $threads, ConversationThreads $store): void
    {
        $thread = $this->requireOpenThread($store);

        if ($thread === null) {
            return;
        }

        $threads->rearm($thread, $this->user());

        Toaster::success('Your assistant can answer this one again.');
    }

    public function render(ConversationThreads $store, AgentThreads $threads): View
    {
        // Refused rather than resolved when there is no tenant, for the reason
        // `Messages` and `Support` give: internal staff belong to no business by
        // design (`28` §9.1), so a signed-in support agent typing this URL is
        // the ordinary way to arrive here with nothing resolved.
        abort_if(Tenancy::id() === null, 403);

        $thread = $this->openThreadId === null ? null : $store->find($this->openThreadId);

        return view('livewire.account.inbox', [
            'threads' => $store->list(),
            'thread' => $thread,
            'messages' => $thread === null ? null : $store->messages($thread),
            // ⚠️ **READ THROUGH THE CONTRACT RATHER THAN OFF THE MODEL, AND
            // THE HONEST CLAIM IS NARROWER THAN IT LOOKS.** `stateFor()`
            // re-reads the row, which is what stops a *stale* object defeating
            // the latch — and on this screen the model was loaded three lines
            // ago in the same request, so the re-read cannot currently differ.
            // A mutation reading `$thread->agent_status` in the Blade instead
            // came back **green**, and that is recorded rather than dressed up:
            // what this line buys today is that the Inbox reads thread state
            // through the one contract that owns it, so P20's campaign half
            // arrives here without a second read path being invented.
            'state' => $thread === null ? null : $threads->stateFor($thread),
        ]);
    }

    /**
     * The open thread, or null with the screen already told why.
     *
     * ⚠️ **404 RATHER THAN A SILENT RETURN FOR AN ID THAT RESOLVES TO
     * NOTHING.** Another tenant's id and a deleted thread are the same answer
     * here, deliberately: telling the two apart would confirm that a row exists
     * in a tenancy the reader has no business knowing about.
     */
    private function requireOpenThread(ConversationThreads $store): ?Conversation
    {
        abort_if(Tenancy::id() === null, 403);

        if ($this->openThreadId === null) {
            return null;
        }

        $thread = $store->find($this->openThreadId);

        abort_if($thread === null, 404);

        return $thread;
    }

    /**
     * What a sender's own refusal or duplicate means to an owner.
     *
     * ⚠️ **`OutreachChannel::Sms`, ON THIS FILE'S OWN REASON ABOVE — 10240,
     * PHASE 2.** Every outcome this class explains came off the Inbox's own
     * text-message thread.
     */
    private function explain(SendOutcome $outcome): string
    {
        return $outcome->reason instanceof SendRefusalReason
            ? $outcome->reason->ownerSentence(OutreachChannel::Sms)
            // The remaining case is a duplicate: the same draft was already
            // accepted, which is the idempotency key doing its job rather than
            // a failure. Saying so is better than a silent no-op on a screen
            // where the message will appear on the next render anyway.
            : 'that reply had already been sent';
    }

    private function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
