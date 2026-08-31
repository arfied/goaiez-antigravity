<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Enums\OperatorAlertKind;
use App\Services\Ops\OperatorAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Something in the platform needs you" — P23's email half.
 *
 * ⚠️ **THE ONLY RECIPIENT IS THE OPERATOR**, at the address in
 * {@see OperatorAlerts::EMAIL_KEY}. This is not tenant-facing mail and must
 * never be sent to a business owner: it names our own internals, and the whole
 * register it is written in assumes the reader can log into a server.
 *
 * ⚠️ **CONSTRUCTED FROM SCALARS RATHER THAN FROM THE `OperatorAlert` MODEL**,
 * on `SupportSessionSummary`'s reasoning: a queued notification serialises its
 * constructor arguments, and a row that has been pruned or edited between the
 * queue and the worker would render as something other than what fired.
 *
 * ⛔ **NOTHING PERSONAL REACHES IT**, because nothing personal is allowed into
 * an alert summary in the first place — see `OperatorAlerts::raise()`. This
 * class adds no lookups of its own precisely so that rule has one place to hold.
 */
final class OperatorAlertRaised extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * Not commercial in any reading: it goes to this platform's own operators
     * about this platform's own health, and it advertises nothing to anybody.
     *
     * ⛔ **AN OPT-OUT ON A PAGER IS THE FAILURE THE PAGER EXISTS TO PREVENT.**
     * T176 R25's whole point is that the alert fires while nobody is watching;
     * an unsubscribe link would let the person least likely to read it silence
     * the one message that says the queue has stopped.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    /**
     * ⚠️ **`$summary` MAY BE LONGER THAN `operator_alerts.summary` AND THAT IS
     * DELIBERATE** (9274). That column is 300 characters because the same
     * sentence has to survive a text message; an email has no column and no
     * handset. {@see OperatorAlerts::email()} hands over the pre-clamp words
     * where {@see OperatorAlerts::clamp()} had to elide any — read off
     * `context.summary_full` on the row, so the *"scalars off the row"* rule
     * above is untouched. **Nothing here may assume 300.**
     */
    public function __construct(
        private readonly OperatorAlertKind $kind,
        private readonly string $subject,
        private readonly string $summary,
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
        $headline = $this->kind->headline();

        $message = (new MailMessage)
            // Prefixed, because this arrives in an inbox beside ordinary
            // platform mail and the operator is meant to be able to filter on
            // it — an alert that reads like a newsletter is one that waits until
            // Monday.
            ->subject('GO AI EZ platform alert: '.$headline)
            ->greeting($headline)
            ->line($this->summary);

        if ($this->subject !== '') {
            $message->line('This is about: '.$this->subject);
        }

        return $message
            ->line('Nothing has been stopped because of this. The platform is still sending, still answering and still charging — this is a notification, not a brake.')
            ->line('You are getting this because your address is set as the platform alert address in Ops settings.');
    }
}
