<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Billing\DunningNotices;
use App\Services\Billing\Subscriptions;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your plan has ended" — the last message of a dunning schedule (6401).
 *
 * `Subscriptions::suspendForNonPayment()` is *"the one place in this application
 * that takes the product away for non-payment"* and until this class existed it
 * took it away in silence. {@see DunningNotices} sends this immediately after
 * that call, on the same tick.
 *
 * ## ⛔ It quotes no money, and that is deliberate (6405)
 *
 * The only figure worth printing here is what starting again costs, and **nobody
 * has decided whether a tenant suspended for non-payment keeps their
 * grandfathered rate.** 3443 promises the price to *an existing customer*, and
 * `suspendForNonPayment()` has just written `SubscriptionStatus::Canceled` onto
 * the row that made them one. Quoting the old rate would make a promise nobody
 * has made; quoting today's would be 3444's defect wearing a different hat. So
 * the notice names no figure at all and the question is raised at 6405 rather
 * than answered here.
 *
 * ⚠️ **THIS IS THE ONE PLACE THE FAILURE NOTICE AND THIS ONE DIVERGE.**
 * {@see PaymentFailed} does quote — the amount it names is a charge that was
 * actually attempted against a subscription that still exists, which is a fact
 * about the past and not an offer about the future.
 *
 * ⚠️ **NO BUTTON, FOR {@see PaymentFailed}'s REASON AND ONE MORE.** There is no
 * card-replacement screen (6404), and the plan page a button would point at says
 * only *"Your plan has ended. Nothing more will be charged."* — true, and not
 * the thing somebody reading this wants to do next.
 *
 * ⚠️ **PLATFORM MAIL, NOT CUSTOMER MAIL**, and not `ShouldQueue` — both for
 * `RenewalReminder`'s reasons.
 */
final class PlanEndedForNonPayment extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(iii) — notice about the status of a subscription the
     * recipient holds. Telling somebody a service they were paying for has
     * stopped is the plainest transactional message this application sends, and
     * it advertises nothing: there is deliberately no price and no offer in it.
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
            ->subject('Your GO AI EZ plan has ended')
            ->greeting('Your plan has ended')
            ->line('We were not able to take the payment for your GO AI EZ plan, so it has '
                .'now ended. Nothing further will be charged.')
            // ⚠️ TRUE TODAY AND NARROW ON PURPOSE. Suspension writes a status and
            // an end date; it deletes nothing, and `TenantDeletion` is a separate
            // path nobody has started. What this must not become is a retention
            // promise — that is `storage.retention_days.*` and the owner's (6200).
            ->line('Nothing has been deleted. Your reviews, your customers and your pages '
                .'are all still here.')
            ->line('Reply to this email and we will get you going again.');
    }
}
