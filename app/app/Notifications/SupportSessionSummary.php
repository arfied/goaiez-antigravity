<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Models\ImpersonationSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "We were in your account, and here is what we did."
 *
 * `28` §9.4: *"Owner notification: email after each act-as session summarizing
 * changes, on by default."* Decision 571 recorded this as a seam rather than a
 * feature because no email layer existed; it does now, and this is the seam
 * closed.
 *
 * ⚠️ **CONSTRUCTED FROM SCALARS, NOT FROM THE MODEL** — and it matters more here
 * than the usual serialisation argument. `ImpersonationSession` is held to one
 * service by an `ArchitectureTest` lint whose claim names *reading*, because
 * nothing beneath the application layer refuses a stray read of where support
 * has been (562, 624). A notification holding the model would be a second reader
 * of that table, arrived at as a side effect of wanting a nicer constructor.
 * `Impersonation` reads the row and hands over the sentence's worth of facts.
 *
 * WHAT IT DELIBERATELY DOES NOT CARRY. No list of the individual changes. The
 * owner already has each one, in their own activity feed, in `28` §9.4's own
 * words — *"GO AI EZ support updated {thing} for you ({ticket ref})"* — written
 * there by `recordWrite()` as each happened. Restating them here means a second
 * rendering of the same facts that can disagree with the first, and it means
 * this email growing without bound on a long session. It carries the count and
 * points at the feed.
 */
final class SupportSessionSummary extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(iii) — information about the recipient's own account.
     *
     * ⛔ **AND IT IS THE ONE MESSAGE ON THIS LIST AN OPT-OUT WOULD BE MOST
     * ATTRACTIVE TO REMOVE.** It tells an owner that support was inside their
     * account, which is the accountability half of `28` §9.1's impersonation
     * rules. A suppressible notice of access is not a notice of access.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    public function __construct(
        private readonly string $agentName,
        private readonly string $reason,
        private readonly ?string $ticketRef,
        private readonly int $changes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Our support team was in your account')
            ->greeting('Someone from GO AI EZ support helped with your account')
            ->line("{$this->agentName} opened your account to: {$this->reason}");

        // ⚠️ Singular and plural spelled out rather than "change(s)". `22`'s
        // copy rules are not decorative here — this email exists to be believed,
        // and the register it is written in is part of that.
        $message->line(match ($this->changes) {
            0 => 'Nothing was changed.',
            1 => 'One thing was changed.',
            default => "{$this->changes} things were changed.",
        });

        if ($this->ticketRef !== null) {
            $message->line("This was about: {$this->ticketRef}");
        }

        if ($this->changes > 0) {
            $message->line('Every change is listed in your activity feed, with the time it happened.');
        }

        return $message->line(
            'If this was not expected, reply to this email and we will look into it.'
        );
    }
}
