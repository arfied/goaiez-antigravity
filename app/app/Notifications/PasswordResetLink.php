<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Mail\PlatformMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that lets somebody choose a new password.
 *
 * ⛔ **THIS CLASS EXISTS BECAUSE THE FRAMEWORK'S OWN ONE CANNOT PASS THE
 * CHOKEPOINT, AND THAT IS THE WHOLE SLICE.**
 * `Illuminate\Auth\Passwords\CanResetPassword::sendPasswordResetNotification()`
 * is a bare `$this->notify(new ResetPasswordNotification($token))` in
 * `vendor/`, so until 9600 the one message `POST /forgot-password` sends went
 * out **without passing {@see PlatformMailer}** — no
 * transport guard, no 24-hour sending ceiling, no pacer, no meter, no CAN-SPAM
 * classification and no `failed_jobs` bell. `App\Models\User`
 * overrides that method now and hands this class to `PlatformMailer::send()`
 * instead. ⚠️ **The ceiling row is named by property rather than by key** —
 * `MailTest` matches that literal anywhere in a file, docblocks included, and
 * `MailQuota` is the one place it is written.
 *
 * ⚠️ **THE FRAMEWORK'S `ResetPassword` COULD NOT SIMPLY BE FORWARDED.** It does
 * not implement {@see ClassifiesUnderCanSpam}, so `deliverNow()` would refuse
 * it outright (`MailNotDeliverable::unclassifiedNotification()`) — correctly,
 * and the fix for that is a first-party class that answers the question rather
 * than a `static::toMailUsing()` callback registered in a service provider,
 * which is a second sender configured three files away from the send.
 *
 * ⚠️ **IT TAKES A BUILT URL RATHER THAN A TOKEN, FOR THE REASON
 * {@see MagicLinkLogin} TAKES ONE.** `PlatformMailer::deliverNow()` routes
 * `Notification::route('mail', $address)`, so the notifiable this class is
 * handed is an `AnonymousNotifiable` and **not** the `User` —
 * `$notifiable->getEmailForPasswordReset()` does not exist on it, and the
 * framework's `resetUrl()` would fatal. The URL is therefore built where the
 * user is, which is the override on the model.
 *
 * ⛔ **QUEUED, SO THE LINK IS WRITTEN TO `jobs.payload` — 704's EXPOSURE, ON A
 * TOKEN THAT LIVES FOUR TIMES AS LONG.** `MagicLinkLogin` records the same
 * trade: a queued notification is serialised whole, constructor arguments
 * included, and a copy lands in `failed_jobs` on the last attempt.
 * ⚠️ **The difference is the clock and it is worth stating rather than
 * inheriting**: a magic link is dead in fifteen minutes, and
 * `config/auth.php`'s `passwords.users.expire` is **60**. `jobs:prune-failed`
 * gives that copy a thirty-day horizon, which is a horizon and not an erasure
 * path. The mitigations are the expiry and single-use consumption — the broker
 * deletes the `password_reset_tokens` row on a successful reset — and the
 * alternative is not queueing, which reopens 702's account-enumeration oracle
 * on an unauthenticated endpoint. **Recorded rather than fixed, and classified
 * in `tests/Feature/Architecture/QueuePayloadTest.php` beside the sign-in
 * link.**
 *
 * ⚠️ **THE COPY IS NOT THE FRAMEWORK'S.** Outcome language, `22`'s rule: the
 * person is told what to do and what happens if they did not ask, never what a
 * token is.
 */
final class PasswordResetLink extends Notification implements ClassifiesUnderCanSpam, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $url,
        private readonly int $minutes,
    ) {}

    /**
     * §7702(17)(A)(ii) — it facilitates a transaction the recipient asked for
     * by typing their address into the reset form.
     *
     * ⛔ **AN UNSUBSCRIBE LINK HERE WOULD BE A LOCK-OUT MECHANISM**, which is
     * {@see MagicLinkLogin}'s argument unchanged: the opt-out this application
     * honours writes a suppression that refuses future sends, and offering it
     * on the message that is somebody's only way back into their own account
     * is a support ticket that cannot be resolved by mail.
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
            ->subject('Choose a new password')
            ->greeting('Choose a new password')
            ->line('Somebody asked to reset the password on your GO AI EZ account. '
                .'Use the button below to choose a new one.')
            ->action('Choose a new password', $this->url)
            ->line("The link works once and expires in {$this->minutes} minutes.")
            ->line('If you did not ask for this, you can ignore this email — your '
                .'password stays as it is and nobody can change it without the link.');
    }
}
