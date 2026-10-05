<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "A caller left a message with your AI receptionist" (AI receptionist plan, wave 3c).
 *
 * ⚠️ It carries neither the message nor the caller's number — `VoicemailReceived`'s rule: those are on the calls page, behind
 * the owner's login, and an email is a copy of personal data in somebody else's inbox.
 */
final class CallMessageLeft extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Somebody left you a message')
            ->greeting('A caller left a message with your AI receptionist')
            ->line('It is on your calls page, with the number they asked you to ring back on.')
            ->action('Open your calls', route('account.calls'));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return ['kind' => 'call_message'];
    }
}
