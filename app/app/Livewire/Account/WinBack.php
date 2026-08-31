<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\TriageStatus;
use App\Models\TriageConversation;
use App\Services\Reviews\ReviewRouter;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * The recovery queue — decision 114's surviving half, given a screen (2689).
 *
 * A customer rated the business at or below `triage_threshold`, so `ReviewRouter`
 * opened a recovery conversation and told the owner something needed them. Until
 * 2026-08-12 that was the end of it: nothing in `app/` could move the status off
 * `Open`, so the recovery path was a row nobody could close and `ProofNumbers`'
 * owner-facing *"customers recovered"* was structurally zero. This is where the
 * owner works one and says what came of it.
 *
 * ⛔ **FIRST-PARTY FEEDBACK ONLY, AND THE TWO PIPELINES ARE NEVER CONFUSED.**
 * `ReviewRouter::route()` refuses anything that is not `ReviewSource::FirstParty`
 * before a conversation can exist, so no Google review can reach this queue —
 * and nothing here holds, hides, approves, moderates or deletes anything. The
 * actions on this screen move a status and store a note; the rating and the words
 * stay exactly where they were, which is 2075's build-failing rule.
 *
 * ⚠️ **WORKED CONVERSATIONS STAY ON THE SCREEN.** They move into a second
 * section rather than disappearing — see `ReviewRouter::recoveryQueue()`. This is
 * the only surface an owner has for feedback that is nowhere else, and a queue
 * that emptied itself as it was worked would show less than was left.
 *
 * ⚠️ **NO CUSTOMER CONTENT REACHES A TOAST.** `masmerise/livewire-toaster` is
 * staff-facing chrome; decision 104 keeps personal data out of it and the audit
 * entry, not the toast, is the record.
 *
 * ⚠️ **A REAL `GET` IS REQUIRED TO PROVE THE ROUTE GATE.** `Livewire::test()`
 * runs no middleware at all (809), so a component test says nothing about who
 * may reach `/account/win-back` — `TriageRecoveryTest` drives the URL as well.
 */
#[Layout('components.account.layout')]
final class WinBack extends Component
{
    /**
     * The note being written, keyed by conversation id.
     *
     * Per row rather than one shared string: the owner may start typing on one
     * card, scroll, and act on another, and a single property would carry the
     * first note onto the second conversation — a customer's private complaint
     * answered with a sentence written about somebody else.
     *
     * @var array<int, string>
     */
    public array $notes = [];

    public function mount(): void
    {
        abort_if(Tenancy::id() === null, 403);
    }

    /**
     * Record what came of a conversation.
     *
     * The status arrives as a string from the button and is resolved through
     * `tryFrom()` — a value that is not one of the four outcomes is a 422 rather
     * than an exception, on `FollowUps::snooze()`'s precedent with its two
     * periods. `Open` is deliberately not reachable here; reopening has its own
     * verb, because "mark this as needing you" is not an outcome.
     */
    public function record(ReviewRouter $router, int $conversationId, string $status): void
    {
        abort_if(Tenancy::id() === null, 403);

        $to = TriageStatus::tryFrom($status);

        if ($to === null || $to->isOpen()) {
            abort(422);
        }

        $conversation = $this->conversation($router, $conversationId);

        // Authorization in a policy (CLAUDE.md), asked with the row so the
        // question is about this conversation rather than about the screen.
        $this->authorize('update', $conversation);

        // Validation through Livewire's own rules, which is this codebase's
        // convention for a component (`Account\Settings`, `Account\Knowledge`,
        // `Setup\ReviewRules`) — a form request has no request to validate here.
        //
        // ⚠️ `nullable`, NOT `required`: the note is compulsory only for a
        // recovery, and that rule lives in `recordTriageOutcome()` rather than
        // here, because it is the writer's rule and a second copy on the screen
        // would leave the service's one unfalsifiable (398). The refusal comes
        // back as a message the owner can act on.
        $this->validate(
            ["notes.{$conversationId}" => ['nullable', 'string', 'max:2000']],
            [],
            ["notes.{$conversationId}" => 'note'],
        );

        try {
            $router->recordTriageOutcome(
                $conversation,
                $to,
                $this->notes[$conversationId] ?? null,
                $this->actor(),
            );
        } catch (InvalidArgumentException $e) {
            Toaster::error($e->getMessage());

            return;
        }

        unset($this->notes[$conversationId]);

        // The label the card will now carry, so the button and the outcome say
        // the same words (`22`: an action keeps its verb through the flow).
        Toaster::success($to->label().'.');
    }

    /**
     * Put a worked conversation back in front of the owner.
     *
     * Its own action rather than a fifth value on `record()`, because it is the
     * one move that is a correction rather than an outcome — and because the
     * stored note is kept: what was tried is still true.
     */
    public function reopen(ReviewRouter $router, int $conversationId): void
    {
        abort_if(Tenancy::id() === null, 403);

        $conversation = $this->conversation($router, $conversationId);

        $this->authorize('update', $conversation);

        $router->recordTriageOutcome($conversation, TriageStatus::Open, null, $this->actor());

        Toaster::success('Back on your list.');
    }

    /**
     * Say who is handling this one — `17` TRIAGE-04's take-over toggle.
     *
     * ⛔ **THIS CHANGES NOTHING ABOUT WHAT THE PLATFORM SENDS, AND THE SCREEN
     * DOES NOT SAY IT DOES** (2706). Nothing in `app/` reads `ai_paused`: the AI
     * triage loop is TRIAGE-01 and is unbuilt, so there is no automated turn to
     * suppress. Copy claiming otherwise would be CLAUDE.md's 314–316 — a
     * protection asserted before it is true, which is what stops the next
     * reviewer looking.
     */
    public function takeOver(ReviewRouter $router, int $conversationId, bool $paused): void
    {
        abort_if(Tenancy::id() === null, 403);

        $conversation = $this->conversation($router, $conversationId);

        $this->authorize('update', $conversation);

        $router->setTriageTakeover($conversation, $paused, $this->actor());

        Toaster::success($paused ? "You're handling this one." : 'Handed back.');
    }

    public function render(ReviewRouter $router): View
    {
        // Refused rather than resolved when there is no tenant, for the reason
        // every sibling gives: internal staff belong to no business by design
        // (`28` §9.1), so a signed-in support agent typing this URL is the
        // ordinary way to arrive here with nothing resolved.
        abort_if(Tenancy::id() === null, 403);

        $all = $router->recoveryQueue();

        return view('livewire.account.win-back', [
            // Grouped by `TriageStatus::isOpen()`, the same predicate the nav
            // badge counts by — a badge saying 3 above a list showing 2 is
            // `FollowUps`' own stated trap.
            'needsYou' => $all->filter(fn (TriageConversation $c): bool => $c->status->isOpen())->values(),
            'worked' => $all->reject(fn (TriageConversation $c): bool => $c->status->isOpen())->values(),
            'outcomes' => [
                TriageStatus::Resolved,
                TriageStatus::NoResponse,
                TriageStatus::Closed,
                TriageStatus::Escalated,
            ],
        ]);
    }

    private function conversation(ReviewRouter $router, int $conversationId): TriageConversation
    {
        $conversation = $router->findConversation($conversationId);

        // A foreign id and a missing one are answered identically on purpose:
        // telling them apart tells a stranger that a row exists.
        abort_if($conversation === null, 404);

        return $conversation;
    }

    private function actor(): string
    {
        return 'user:'.(string) auth()->id();
    }
}
