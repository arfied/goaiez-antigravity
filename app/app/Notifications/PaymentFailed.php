<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Billing\Dunning;
use App\Services\Billing\DunningNotices;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "We could not take the payment for your plan" — the dunning notice (6400).
 *
 * ⛔ **THE HALF {@see Dunning} SAID OUT LOUD IT DID NOT HAVE.** Its own docblock
 * reads *"a schedule of three silent attempts and then a suspension is a tenant
 * losing the product with no warning… the notification is owed and is not built
 * here"*, and 2590 raised that debt rather than settling it by making the
 * schedule actually run. This is that notice. {@see DunningNotices} decides when
 * it goes; this class is only the words.
 *
 * ## ✅ It names an action, and it did not until 2026-08-21 (6404, 6517, 6580)
 *
 * ⛔ **THIS SECTION SAID THE OPPOSITE AND EVERY SENTENCE OF IT WAS TRUE WHEN
 * WRITTEN — BOTH READINGS KEPT AND DATED.** It read: *"The obvious copy is
 * 'Update your card' over a button, and **there is nowhere for that button to
 * go.** `AuthorizeNetGateway::replacePaymentMethod()` has no caller anywhere in
 * `app/`: no route, no controller, no screen. The one card form that exists,
 * `billing.card`, is a **signup** screen, and `subscribe()` throws outright for
 * any business that already holds a subscription id — which is every business
 * this notice is sent to. So a button there would render, be pressed by somebody
 * trying to save their plan, and 500."*
 *
 * ✅ **The screen exists** (6500–6519). `PaymentMethodReplacement` is
 * `replacePaymentMethod()`'s first caller, reached from `Account\Plan`, and the
 * button here points at it. **The 500 that paragraph predicts is pinned by a
 * mutation in `PlanScreenCardTest`**: swapping the call back to `subscribe()`
 * reddens with seven errors at `AuthorizeNetGateway.php:177`, which is that
 * sentence still being true about the path this one deliberately avoids.
 *
 * ⚠️ **THE RULE THE OLD SECTION RESTED ON IS UNCHANGED AND STILL BINDING** —
 * *"naming an action the owner cannot take is worse jargon than naming a
 * mechanism"* (`AutopilotActionType::OwnerActionNeeded`'s house position). What
 * changed is that the action became takeable. ⛔ **Which is why
 * {@see PlanEndedForNonPayment} DOES NOT get this button**: 6508 refused
 * restarting an ended plan, because `subscribe()` cannot accept a row holding a
 * dead subscription id, so a card screen would be exactly the unusable action
 * this rule forbids. That notice still asks them to reply, deliberately.
 *
 * ⚠️ **The reply route survives as the second line rather than the only one.**
 * It reaches `SupportMailbox` and a person, and it is what a tenant whose card
 * is not to hand still needs. ⚠️ **And the button promises a screen, never an
 * outcome**: whether replacing the card actually recovers the subscription
 * depends on Automatic Retry, a merchant-account setting **no API can read**
 * (6506), so nothing here says when the payment will be taken.
 *
 * ## The amount is the price they agreed to, or no amount at all
 *
 * ⛔ **`PlanCharges::agreedPriceFor()`, NEVER a live quote** (3443, 3444) — the
 * defect that method exists to prevent is a statutory notice quoting today's
 * registry figure at somebody who bought at a different one, and *"we could not
 * take $X"* is the same claim in a stronger form: it states what was charged.
 * ⚠️ **On a row that predates the agreed-price columns the amount is withheld
 * entirely** rather than falling back to the registry — see
 * {@see DunningNotices::amountFor()} for why this goes further than
 * `Livewire\Account\Plan`, which withholds only the promise (4844).
 *
 * ⚠️ **PLATFORM MAIL, NOT CUSTOMER MAIL** — `RenewalReminder`'s reasoning
 * exactly. The recipient is the account holder, the account relationship is the
 * authorisation, and `PlatformMailer::send()` is the path.
 *
 * ⚠️ **NOT `ShouldQueue`, DELIBERATELY** — `PlatformMailer::send()` already
 * dispatches `DeliverPlatformMail`, which calls `notifyNow()`; queueing here
 * would put the message back on the queue past the deliverability guard that job
 * exists to run.
 */
final class PaymentFailed extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(iii) — a notice about a subscription the recipient already
     * holds, about a charge against it.
     *
     * ⚠️ **THE STRONGEST TRANSACTIONAL CASE IN THIS DIRECTORY.** `29` §2 rule
     * 43's surviving half is *never bill by surprise* (3294), and this message
     * is the only thing standing between a tenant and losing the product over a
     * payment they were never told had failed. A notice an unsubscribe could
     * suppress would defeat the rule it exists to serve.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    /**
     * @param  ?string  $amount  the agreed price, formatted — or null when the
     *                           row predates the agreed-price columns
     * @param  ?string  $endsOn  the date the plan ends if nothing changes, on
     *                           the last notice before suspension and on no other
     */
    public function __construct(
        private readonly ?string $amount = null,
        private readonly ?string $endsOn = null,
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
        $mail = (new MailMessage)
            ->subject('We could not take the payment for your GO AI EZ plan')
            ->greeting('Your payment did not go through');

        // ⚠️ ONE SENTENCE IN TWO SHAPES, RATHER THAN A SENTENCE WITH A HOLE IN
        // IT. A withheld amount must never leave "the payment of  for your plan".
        $mail->line($this->amount === null
            ? 'The payment for your GO AI EZ plan did not go through.'
            : 'The payment for your GO AI EZ plan — '.$this->amount.' — did not go through.');

        // ⛔ NEVER "we will try again". `Dunning`'s docblock settles it (2143): a
        // retry on this gateway is a prompt and not a charge, because charging
        // the stored profile directly would take the money without reinstating
        // the suspended subscription. Copy promising another attempt would
        // describe something this application does not do.
        $mail->line('Everything on your account is still running. The payment needs a '
            .'working card behind it before it can go through.');

        $mail->line($this->endsOn === null
            // ⚠️ NO DATE UNTIL THE LAST NOTICE, AND THAT IS HONESTY RATHER THAN
            // VAGUENESS. An unreachable gateway does not count against the
            // tenant (2148), so the suspension date genuinely moves until the
            // final attempt is written. Naming one earlier would be a date this
            // application breaks by design.
            ? 'If it stays unpaid your plan will end, and we will write to you with '
                .'the date before that happens.'
            : 'If it is still unpaid on '.$this->endsOn.', your plan will end.');

        return $mail
            ->action('Update your card', route('account.plan'))
            ->line('Or reply to this email and we will take a new card from you.');
    }
}
