<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "We answered your support request" (T137 `SL-7`).
 *
 * ⚠️ **IT CARRIES THE SUBJECT AND NOT THE REPLY, AND THAT IS NOT CAUTION FOR ITS
 * OWN SAKE.** The subject is the tenant's own words going back to the tenant's
 * own address; our answer may name what we changed, what we found in their
 * account, or what somebody else on their staff told us. Mail is forwarded,
 * quoted and left open on shared screens — the thread is behind a sign-in, and
 * this is the pointer to it.
 *
 * Constructed from a scalar rather than from the ticket, `SupportSessionSummary`'s
 * rule: a queued payload outlives the row it describes, and `SupportTicket` is
 * held to one service by a chokepoint lint, so a notification holding the model
 * would be a second reader of the table arrived at through a nicer constructor.
 */
final class SupportReplyPosted extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(v) — a reply to a support request the recipient opened. The
     * relationship is not merely existing, it is the one they started this
     * week, and an opt-out would silence the answer to their own question.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    public function __construct(private readonly string $subject) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We replied to your support request')
            ->greeting('We have replied')
            ->line("You asked us about: {$this->subject}")
            // Outcome language (`22`), and a route name rather than a built URL
            // — `CLAUDE.md`'s rule and the reason a moved path never becomes a
            // dead link in an email nobody can edit afterwards.
            ->action('Read our reply', route('account.support'))
            ->line('Replying on that screen brings the thread back to us.');
    }
}
