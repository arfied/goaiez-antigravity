<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Ops\OperatorAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Nothing is wrong — somebody is testing whether this reaches you."
 *
 * ⛔ **A SEPARATE NOTIFICATION FROM {@see OperatorAlertRaised}, AND THE REASON
 * IS THE SAME ONE THAT MADE THE PROBE WRITE NO ROW.** Reusing the alert mail
 * would prove marginally more — the exact template a real page renders — and it
 * would do so by putting a **fabricated incident in the operator's inbox**,
 * subject line and all, with a headline borrowed from a kind describing
 * something that is not happening. That is the pollution
 * {@see OperatorAlerts::probeChannels()} exists to avoid, one channel over: a
 * row in `operator_alerts` can at least be reasoned about, and a message
 * already read at 3am cannot be taken back.
 *
 * ⚠️ **WHAT IS GENUINELY LOST BY NOT REUSING IT IS SMALL AND IS STATED RATHER
 * THAN WAVED AWAY.** `OperatorAlertRaised::toMail()` builds strings and looks
 * nothing up; everything that could actually break — the mail transport, the
 * from address, decision 5500's sending-domain rule, the daily ceiling, the
 * CAN-SPAM classification, `PlatformMailContext`, the framework's own mail
 * template — is shared, and this message goes through every one of them.
 *
 * ⚠️ **THE FIRST LINE SAYS NOTHING IS WRONG, BEFORE ANYTHING ELSE.** `22`'s
 * outcome language, applied to the one message in this application whose reader
 * may be half asleep and has been trained by every other message on this
 * address to assume the platform is on fire.
 */
final class OperatorAlertProbeSent extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * ⛔ **AN OPT-OUT ON A PAGER TEST IS AN OPT-OUT ON THE PAGER**, which is
     * {@see OperatorAlertRaised}'s argument unchanged: it goes to this
     * platform's own operator, about this platform's own health, at the address
     * in {@see OperatorAlerts::EMAIL_KEY} which they typed in themselves, and it
     * advertises nothing to anybody.
     */
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
            // Prefixed like a real alert so one inbox filter catches both, and
            // then immediately contradicted, because the subject line is the
            // whole of what a phone shows on a lock screen.
            ->subject('GO AI EZ pager test — nothing is wrong')
            ->greeting('Nothing is wrong')
            ->line('Somebody ran the pager test on the platform. This message exists only to prove that an alert sent to this address arrives.')
            ->line('If you are reading this, the email half of the pager works: the transport, the sending address and the daily ceiling are all in order, and a real alert would reach you the same way.')
            ->line('Nothing was recorded as an incident. No alert row was written and nothing was logged at critical level, so the operator alert board is unchanged.')
            ->line('You are getting this because your address is set as the platform alert address in Ops settings.');
    }
}
