<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one message `mail:probe-ses-simulator` ever sends (10280–10289).
 *
 * ⚠️ **NOT `ShouldQueue`, ON `ProbeOperatorAlertChannels`' PRECEDENT.** The
 * command reports the transport's own accept/refuse for this one send, and a
 * queued notification would report "queued" — answering the question with the
 * question, exactly as that command's docblock argues about its own probe
 * message. `PlatformMailer::deliverNow()` is built for a notification sent this
 * way.
 *
 * ⚠️ **THE RECIPIENT IS NOT A PERSON.** It is one of AWS's own mailbox
 * simulator addresses — `success@`, `bounce@`, `ooto@`, `complaint@` or
 * `suppressionlist@simulator.amazonses.com` — which AWS's own documentation
 * says exist purely to answer with a synthetic SNS notification. There is
 * nobody to protect from an unsubscribe link they cannot act on, which is why
 * this is classified transactional rather than commercial: the CAN-SPAM
 * question this class answers is about a human reader, and this message has
 * none.
 */
final class SesFeedbackProbe extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    public function __construct(
        private readonly string $scenario,
    ) {}

    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

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
            ->subject('GO AI EZ — SES feedback probe ('.$this->scenario.')')
            ->greeting('Nothing is wrong')
            ->line('This message was sent deliberately, by an operator running `php artisan '
                .'mail:probe-ses-simulator` against AWS\'s own mailbox simulator, to prove that a '
                .'genuine SES `Notification` event reaches /webhooks/ses and verifies.')
            ->line('Scenario: '.$this->scenario)
            ->line('Sent at: '.now()->toIso8601String())
            ->line('If you are reading this in an inbox, this message was NOT accepted by the '
                .'simulator — the simulator answers with a synthetic notification and never delivers '
                .'to a real mailbox. Seeing this delivered means it went somewhere other than AWS\'s '
                .'simulator, which should not be possible given the command\'s own allowlist.');
    }
}
