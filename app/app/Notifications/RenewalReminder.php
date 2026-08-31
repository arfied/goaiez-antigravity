<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Billing\RenewalReminders;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your plan renews on …" — California's pre-renewal notice (2980–2999).
 *
 * The Automatic Renewal Law requires a subscription with a term of a year or
 * longer to be given notice 15–45 days before it renews, and the notice to carry
 * the fact that it renews, when, for how much, and how to stop it. All four are
 * below, in that order, because that is the order somebody reads them in.
 *
 * ⚠️ **CONSTRUCTED FROM SCALARS, NOT FROM `Subscription`** —
 * `TenantExportReady`'s reason, sharper here: `subscriptions` is held to one
 * writer by a chokepoint lint, and a notification holding the model would be a
 * second reader arrived at as a side effect of a nicer constructor.
 * {@see RenewalReminders} reads the row and hands over
 * three strings.
 *
 * ⚠️ **PLATFORM MAIL, NOT CUSTOMER MAIL.** The recipient is the account holder
 * and the account relationship is the authorisation — `PlatformMailer::send()`,
 * never `sendToCustomer()`, and no consent record exists or should. It is also
 * why open question H does not block it: `assertCustomerMailPermitted()` guards
 * the other method.
 *
 * ⚠️ **NOT `ShouldQueue`, DELIBERATELY.** `PlatformMailer::send()` already
 * dispatches `DeliverPlatformMail`, which calls `notifyNow()`; a queueable
 * notification would put the message back on the queue *past* the deliverability
 * guard that job exists to run. Every other notification in this application is
 * shaped the same way.
 */
final class RenewalReminder extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(iii) — notice about a subscription the recipient already
     * holds, including the amount and the date it will be charged.
     *
     * ⚠️ **IT QUOTES A PRICE AND THAT DOES NOT MAKE IT AN ADVERTISEMENT.**
     * The statute's transactional class explicitly covers *"information
     * regarding a subscription … including notification of changes in terms or
     * features"*; the primary purpose here is telling somebody money is about
     * to leave their account. **Suppressing it would be the surprise charge
     * `29` §2 rule 43's surviving half forbids** (3294), which is the strongest
     * argument on this list for the transactional answer.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    public function __construct(
        private readonly string $renewsOn,
        private readonly string $price,
        private readonly string $cancelUrl,
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
        return (new MailMessage)
            ->subject('Your GO AI EZ plan renews on '.$this->renewsOn)
            ->greeting('Your plan renews soon')
            ->line('This is the reminder we promised when you signed up: your plan renews '
                .'automatically, so you do not have to do anything to keep it.')
            ->line('It renews on '.$this->renewsOn.', for another year, and '.$this->price
                .' will be charged to the card we hold.')
            ->line('If you would rather it did not, you can end it yourself at any time — '
                .'there is no cancellation fee and no notice period.')
            ->action('End my plan', $this->cancelUrl)
            ->line('If you are happy to carry on, there is nothing to do. Reply to this '
                .'email if you would like a hand with anything.');
    }
}
