<?php

declare(strict_types=1);

namespace App\Livewire\Support;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportDesk;
use App\Services\Support\SupportMacros;
use App\Support\Admin\SupportAccess;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Ops → Support → Requests — the console half of T137 `SL-7`.
 *
 * What is waiting on us, oldest first, and one thread at a time. The queue rows
 * carry a business id, a channel and three timestamps and **nothing anybody
 * wrote**; the words appear only once an agent opens a thread, which is a read
 * inside that tenant's own tenancy.
 *
 * ⚠️ **NO `Tenancy::actingAs()` AND NO `Business::` HERE** — `Architecture\
 * StaffTest` holds the support console to its services, and this screen is one
 * more file that would otherwise be a cross-tenant read nothing records. Every
 * read and every write goes through {@see SupportDesk}.
 *
 * ⚠️ **THE QUEUE DOES NOT SHOW THE SUBJECT LINE, AND THAT IS A CHOICE RATHER
 * THAN AN OMISSION.** A subject is text a person wrote; putting it on a list
 * screen means the tenant's own words are readable outside their tenancy, in a
 * table with no row-level security, by everyone the gate admits. The account
 * number and the channel are enough to pick up work, and opening the thread is
 * one click — decision 800's shape: this lists work waiting, never accounts to
 * browse.
 */
final class Tickets extends Component
{
    public ?int $openTicketId = null;

    public string $reply = '';

    public ?int $confirmingResolveId = null;

    public function bodyLimit(): int
    {
        return app(SupportDesk::class)->bodyLimit();
    }

    public function mount(): void
    {
        $this->authorize(SupportAccess::GATE);
    }

    public function open(int $ticketId): void
    {
        $this->authorize(SupportAccess::GATE);

        $this->openTicketId = $ticketId;
        $this->reply = '';
        $this->confirmingResolveId = null;
        $this->resetErrorBag();
    }

    public function back(): void
    {
        $this->reset(['openTicketId', 'reply', 'confirmingResolveId']);
        $this->resetErrorBag();
    }

    /**
     * Paste one macro into the reply box — T308 §A2's composer half.
     *
     * ⚠️ **IT APPENDS RATHER THAN REPLACING.** An agent who has typed a sentence
     * and then reaches for a macro loses the sentence otherwise, and the undo for
     * a `wire:model` overwritten from the server is retyping it.
     *
     * ⚠️ **THE KEY IS LOOKED UP AGAINST `insertable()` RATHER THAN FETCHED
     * DIRECTLY**, so a macro whose bound sentence is unset cannot be pasted by
     * anybody who guesses its key from the markup — the same list the buttons
     * come from, which is what makes the button's absence a real refusal rather
     * than a hidden control.
     */
    public function insertMacro(string $key, SupportMacros $macros): void
    {
        $this->authorize(SupportAccess::GATE);
        $this->authorize('answer', SupportTicket::class);

        $macro = collect($macros->insertable())->firstWhere('key', $key);

        if ($macro === null) {
            return;
        }

        $this->reply = trim($this->reply) === ''
            ? $macro['body']
            : rtrim($this->reply)."\n\n".$macro['body'];
    }

    public function answer(SupportDesk $desk): void
    {
        // ⚠️ TWO CHECKS, AND NEITHER IS THE OTHER'S DUPLICATE. The gate says
        // who may be on this screen at all; the policy says who may speak in
        // the platform's name — see `SupportTicketPolicy` for why "cannot reach
        // the screen" is not the same fact.
        $this->authorize(SupportAccess::GATE);
        $this->authorize('answer', SupportTicket::class);

        if ($this->openTicketId === null) {
            return;
        }

        $this->validate([
            'reply' => ['required', 'string', 'min:2', 'max:'.$this->bodyLimit()],
        ]);

        try {
            $desk->answer($this->openTicketId, $this->user(), $this->reply);
        } catch (InvalidArgumentException $e) {
            $this->addError('reply', $e->getMessage());

            return;
        }

        $this->reply = '';

        // No account name, no subject, no words from the thread — decision 104.
        Toaster::success('Replied — the account has been told');
    }

    public function confirmResolve(int $ticketId): void
    {
        $this->authorize(SupportAccess::GATE);
        $this->authorize('resolve', SupportTicket::class);

        $this->confirmingResolveId = $ticketId;
    }

    public function dismissConfirm(): void
    {
        $this->confirmingResolveId = null;
    }

    /**
     * Close a thread on the tenant's behalf.
     *
     * ⚠️ **CONFIRMED, WHERE REPLYING IS NOT.** 1228's rule — confirm the
     * direction that is hard to notice you took. A reply is visible to the
     * tenant the moment it lands and is undone by writing another one; closing
     * takes their request off this queue, and the only person who finds out is
     * the one who comes back to a thread nobody is working on.
     */
    public function resolve(SupportDesk $desk): void
    {
        $this->authorize(SupportAccess::GATE);
        $this->authorize('resolve', SupportTicket::class);

        if ($this->confirmingResolveId === null) {
            return;
        }

        try {
            $desk->resolveAsStaff($this->confirmingResolveId, $this->user());
        } catch (InvalidArgumentException $e) {
            $this->addError('queue', $e->getMessage());
            $this->confirmingResolveId = null;

            return;
        }

        Toaster::success('Closed');

        $this->reset(['openTicketId', 'reply', 'confirmingResolveId']);
    }

    public function render(SupportDesk $desk, SupportMacros $macros): View
    {
        $this->authorize(SupportAccess::GATE);

        $user = auth()->user();

        return view('livewire.support.tickets', [
            'waiting' => $desk->waiting(),
            'thread' => $this->openTicketId === null ? null : $desk->openForStaff($this->openTicketId),
            'mayAnswer' => $user?->can('answer', SupportTicket::class) ?? false,
            'mayResolve' => $user?->can('resolve', SupportTicket::class) ?? false,
            // Titles and keys only reach the markup; the bodies ride along
            // because pasting one is a round trip to this component and the
            // list is eleven rows of our own words, not a tenant's data.
            'macros' => $macros->insertable(),
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new InvalidArgumentException('Not signed in.');
        }

        return $user;
    }
}
