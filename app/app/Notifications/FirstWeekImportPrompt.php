<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Bring in your past customers." Day 0-1 of the First 7-Day Results Path
 * (`28` §3.2).
 *
 * ⚠️ **ONE EMAIL, NOT "ONE SMS + ONE EMAIL" — SEE `FirstWeekPath`'s DOCBLOCK.**
 * `28` §3.2 asks for both channels and this ships the email half only.
 * `FirstWeekPath::runDay1()` sends it once, ever, per tenant — never repeated —
 * which is the "then stop" half of §3.2's rule kept even though the SMS half of
 * the pair is not.
 *
 * ⛔ **THE REASON GIVEN HERE WAS FALSIFIED BY WAVE 38 AND IS KEPT DATED RATHER
 * THAN DELETED (4368) — CORRECTED 2026-08-28 (10950).** It read: *"there is no
 * owner-authorised SMS category and no column recording an owner's phone
 * number."* **Both halves are now false**: `owner_notify_numbers.e164` is that
 * column, and `OwnerSendPermit` — minted by `OwnerConsentService` from a
 * recorded consent event, on the owner ruling of 2026-08-27 (10540) — is that
 * category.
 *
 * ⛔ **AND WHAT REPLACES IT IS A HARDER REASON RATHER THAN A SOFTER ONE**
 * (10941). This is a **prompt to do something**, not an account notice: the
 * disclosure an owner agreed to (`OwnerNotifyDisclosure::TEXT`) covers *"an
 * urgent message from a customer, or something that needs your reply"*, and a
 * day-1 onboarding nudge is neither. **So the SMS half is now refused on the
 * scope of the consent rather than on the absence of a mechanism**, which is a
 * question for the owner and not an engineering gap. Do not read the mechanism
 * arriving as this half becoming buildable.
 *
 * ⚠️ **SKIPPABLE, AND CHECKED BEFORE THIS CLASS EVER CONSTRUCTS**, on §3.2's own
 * rule that a step is "skippable if its win already happened organically" — an
 * owner who has already imported customers by the time this would send gets
 * nothing, because the prompt would be asking for something already done.
 */
final class FirstWeekImportPrompt extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(v) — it tells the recipient how to use the service they have
     * already signed up for, and sells them nothing they do not already have.
     *
     * ⚠️ **THIS IS THE CLOSEST CALL OF THE TWELVE AND IT IS RECORDED AS ONE**
     * (decision 4018). It is a nudge, it is sent during a trial, and "drive
     * adoption so they convert" is a commercial motive — read that way the
     * primary purpose test tips the other way. What settles it is that the
     * content advertises no product: the action it asks for is a setup step
     * inside the account, `FirstWeekPath::runDay1()` sends it **once ever** with
     * no follow-up sequence, and it is skipped entirely for an owner who has
     * already imported.
     *
     * ⛔ **AND THE ALTERNATIVE IS NOT AVAILABLE TODAY ANYWAY**, which is the
     * fact worth carrying forward: this goes to an account holder through
     * `PlatformMailer::send()`, and no suppression store exists for a `User`.
     * Classing it commercial would not add an unsubscribe link — it would refuse
     * the send (`MailNotDeliverable::commercialWithoutOptOut()`). ⚠️ **So the
     * day a genuine owner-facing marketing email is written, this decision has
     * to be re-opened *with* the store**, and the refusal is what will force it.
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
            ->subject('Bring in your past customers')
            ->greeting('One thing worth doing early')
            ->line('Adding your past customers means we can invite the happy ones to leave a review.')
            ->action('Bring in your customers', route('account.customers.import'))
            ->line('This takes a couple of minutes, and you can do it whenever suits you.');
    }
}
