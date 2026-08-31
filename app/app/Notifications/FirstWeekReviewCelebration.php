<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You got your first Google review." The First 7-Day Results Path's actual
 * win (`28` §3.2, Day 2-3) — everything else in the path exists to reach this
 * or to stand in for it.
 *
 * ⚠️ **SENT ON WHATEVER TICK NOTICES IT, NOT ON A FIXED DAY.** `FirstWeekPath`
 * checks for this on every `advance()`, independent of which day-step it is
 * processing — §3.2's own rule that a step is "skippable if its win already
 * happened organically" only means something if the win itself is detected as
 * soon as it happens, rather than waiting for a scheduled day to look.
 *
 * ⚠️ **THE REVIEW'S OWN WORDS NEVER APPEAR HERE.** They are not this
 * notification's to hold — the owner reads them on Google, and copying them
 * into an email is a second, uncontrolled place a review's text could drift
 * from the source the platform is not allowed to touch (`29` §2: Google reviews
 * cannot be held, hidden, approved, or moderated). This says that one arrived,
 * never what it said.
 */
final class FirstWeekReviewCelebration extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(v) — notice of something that happened on the recipient's
     * own listing. A results notification about their own account.
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
            ->subject('You got your first Google review')
            ->greeting('Good news')
            ->line('Someone just left you a review on Google.')
            ->line('Every one you get from here makes the next customer a little more likely to choose you.');
    }
}
