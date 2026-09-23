<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\User;
use App\Services\Support\SupportDesk;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * "Ask us something" — the tenant's half of T137 `SL-7`.
 *
 * The whole desk on one screen: what you have asked, what we said, and a box to
 * ask something new. Threads are opened in place rather than at their own URL —
 * a support conversation is one contact rather than a destination
 * (`account.customers.show`'s distinction, `OwnerNav`), and a second route would
 * be a second thing to authorise for no gain.
 *
 * ⚠️ **NOTHING HERE NAMES A TABLE.** {@see SupportDesk} is the only reader and
 * writer of the three, held by a chokepoint lint — so the tenant boundary on
 * every read is the global scope plus RLS, in one place, rather than a `where`
 * this component could forget.
 *
 * ⚠️ **A TICKET ID ARRIVING FROM THE BROWSER IS NOT TRUSTED AND DOES NOT NEED TO
 * BE.** A Livewire action is callable whatever rendered it (391), so `open(9)`
 * for another tenant's thread is one line to attempt — and it finds nothing,
 * because every read runs inside this tenant's own tenancy. The test drives that
 * directly rather than asserting the button is absent.
 */
#[Layout('components.account.layout')]
final class Support extends Component
{
    public string $subject = '';

    public string $body = '';

    public ?int $openTicketId = null;

    public string $reply = '';

    public function subjectLimit(): int
    {
        return app(SupportDesk::class)->subjectLimit();
    }

    public function bodyLimit(): int
    {
        return app(SupportDesk::class)->bodyLimit();
    }

    public function open(int $ticketId): void
    {
        $this->openTicketId = $ticketId;
        $this->reply = '';
        $this->resetErrorBag();
    }

    public function back(): void
    {
        $this->reset(['openTicketId', 'reply']);
        $this->resetErrorBag();
    }

    public function raise(SupportDesk $desk): void
    {
        $this->requireTenant();

        $this->validate([
            'subject' => ['required', 'string', 'min:3', 'max:'.$this->subjectLimit()],
            'body' => ['required', 'string', 'min:3', 'max:'.$this->bodyLimit()],
        ]);

        $ticket = $desk->raise($this->user(), $this->subject, $this->body);

        $this->reset(['subject', 'body']);
        $this->openTicketId = (int) $ticket->id;

        // ⚠️ NO SUBJECT LINE AND NO NAME IN THE TOAST. Toast text carries no
        // personal data and is never the audit record (decision 104); the
        // record is in `audit_log` and the thread is on the screen behind it.
        Toaster::success('Sent — we will reply here');
    }

    public function send(SupportDesk $desk): void
    {
        $this->requireTenant();

        if ($this->openTicketId === null) {
            return;
        }

        $this->validate([
            'reply' => ['required', 'string', 'min:2', 'max:'.$this->bodyLimit()],
        ]);

        try {
            $desk->replyAsTenant($this->openTicketId, $this->user(), $this->reply);
        } catch (InvalidArgumentException $e) {
            $this->addError('reply', $e->getMessage());

            return;
        }

        $this->reply = '';

        Toaster::success('Sent — we will reply here');
    }

    public function close(SupportDesk $desk): void
    {
        $this->requireTenant();

        if ($this->openTicketId === null) {
            return;
        }

        try {
            $desk->resolveAsTenant($this->openTicketId, $this->user());
        } catch (InvalidArgumentException $e) {
            $this->addError('reply', $e->getMessage());

            return;
        }

        Toaster::success('Closed — replying opens it again');
    }

    public function render(SupportDesk $desk): View
    {
        // Refused rather than resolved when there is no tenant, for the reason
        // `Messages` gives: internal staff belong to no business by design
        // (`28` §9.1), so a signed-in support agent typing this URL is the
        // ordinary way to arrive here with nothing resolved.
        abort_if(Tenancy::id() === null, 403);

        $thread = $this->openTicketId === null ? null : $desk->thread($this->openTicketId);

        return view('livewire.account.support', [
            'tickets' => $desk->mine(),
            'thread' => $thread,
        ]);
    }

    private function requireTenant(): void
    {
        abort_if(Tenancy::id() === null, 403);
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
