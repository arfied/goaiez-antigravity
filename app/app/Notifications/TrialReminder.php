<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Billing\TrialReminders;
use App\Support\Messaging\LifecycleLadderCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The no-card free trial's warning ladder, and its landing (9395).
 *
 * ⛔ **THE SUBJECT AND THE GUARANTEE LINE ARE THE CANON's AND THE BODY IS THIS
 * FILE's, AND THAT SPLIT IS NOT A CONVENIENCE.**
 * {@see LifecycleLadderCatalog} carries a subject, an SMS line and an optional
 * email *insert* for each rung — it carries **no email body**, and it never has.
 * So a sender either writes the body or sends a subject with nothing under it.
 * `RenewalReminder` is the house precedent: the words a person reads are
 * authored in the notification, and the canon supplies the parts it actually
 * holds.
 *
 * ⛔ **R48c's WORD LAW APPLIES TO THE BODY EVEN THOUGH NO CANON SWEEP REACHES
 * IT.** `seededCustomerFacingStrings()` iterates the catalogue, so every string
 * below is outside the one instrument that polices *"paused, never expired"* —
 * which is exactly the reason `TrialReminderTest` asserts the word law over the
 * **rendered** message rather than over the catalogue.
 *
 * ⛔ **IT NAMES A CONTROL AND IT DOES NOT PRESS FOR ONE.** The trial is no-card
 * by ruling (2065) and the card is optional by ruling; every body here states
 * that doing nothing costs the person nothing they cannot get back, in the same
 * words `resources/views/livewire/account/credit.blade.php` uses, because two
 * surfaces describing one clock differently is worse than one surface saying
 * nothing. ⚠️ **The link is not a purchase and CONFIRM does not attach to it** —
 * `CLAUDE.md` reserves CONFIRM for the act that spends money, and this is a link
 * to the screen where that act begins.
 *
 * ⚠️ **CONSTRUCTED FROM SCALARS, NOT FROM `Subscription`** — `RenewalReminder`'s
 * reason exactly: `subscriptions` is held to one writer by a chokepoint lint,
 * and a notification holding the model would be a second reader arrived at as a
 * side effect of a nicer constructor. {@see TrialReminders} reads the row.
 *
 * ⚠️ **NOT `ShouldQueue`, DELIBERATELY.** `PlatformMailer::send()` already
 * dispatches `DeliverPlatformMail`, which calls `notifyNow()`; a queueable
 * notification would put the message back on the queue *past* the deliverability
 * guard that job exists to run.
 */
final class TrialReminder extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(iii) — notice about a subscription the recipient already
     * holds, and specifically about a **change in its status**.
     *
     * ⚠️ **THE SUBJECT IS THE SERVICE STOPPING, NOT AN OFFER.** Every body here
     * exists to tell somebody that a thing they are using is about to pause, or
     * has paused, on a date this application chose. Suppressing that as
     * marketing would leave a person to discover it by finding the product
     * broken — the surprise `29` §2 rule 43's surviving half forbids (3294), and
     * the reason this whole lane exists.
     *
     * ⚠️ **THE CARD LINK DOES NOT MOVE IT TO `Commercial`.** So does
     * `RenewalReminder`'s cancellation link, and the classification follows the
     * message's primary purpose rather than the presence of a control.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    /**
     * @param  string  $subject  the rung's authored subject, already composed
     * @param  ?string  $guarantee  the rung's resolved email insert, or null
     *                              where this rung carries none
     * @param  string  $endsOn  the day the trial runs out, already formatted
     * @param  bool  $hasPaused  whether the pause has already happened — this
     *                           is what chooses the tense, and getting it wrong
     *                           is the whole of what the landing rung's `-1`
     *                           trigger exists to prevent
     */
    public function __construct(
        private readonly string $subject,
        private readonly ?string $guarantee,
        private readonly string $endsOn,
        private readonly bool $hasPaused,
        private readonly string $cardUrl,
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
        $message = (new MailMessage)->subject($this->subject);

        $message = $this->hasPaused
            ? $message
                ->greeting('Your free trial has finished')
                ->line('Your GO AI EZ free trial ran until '.$this->endsOn.', and sending and '
                    .'publishing are now paused.')
                // ⛔ R48c: **paused**, never "expired", and the sentence has to
                // earn the word rather than only use it. Nothing is deleted and
                // nothing was charged, which is what makes "paused" true.
                ->line('Nothing has been deleted and nothing has been charged. Your account, your '
                    .'settings, your conversations and your credits are all exactly where you left '
                    .'them, and adding a card starts everything again.')
            : $message
                ->greeting('Your free trial')
                ->line('Your GO AI EZ free trial runs until '.$this->endsOn.'. Everything is on and '
                    .'nothing is being charged.')
                // The same two facts, in the same order, as the countdown on
                // `/account/credit` and `/account/plan`. One clock, one sentence.
                ->line('If you do nothing, sending and publishing pause on that date and your '
                    .'account stays exactly as it is.');

        if ($this->guarantee !== null) {
            // The written guarantee promise, in canonical words, resolved from
            // `legal.guarantee_sentence` by `LifecycleLadder` before it ever
            // reached this constructor. ⛔ It is never re-typed here — see
            // `OneSourceTest`, which enumerates every file permitted to spell it.
            $message = $message->line($this->guarantee);
        }

        return $message
            ->action('Add a card', $this->cardUrl)
            ->line('There is nothing to do if you would rather leave it. Reply to this email if '
                .'you would like a hand with anything.');
    }
}
