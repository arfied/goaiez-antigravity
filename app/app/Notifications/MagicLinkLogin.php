<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that is a login.
 *
 * QUEUED, and that matters more than it looks. `29` §2 rule 41 forbids slow work
 * on a synchronous path, and the request that triggers this must return in the
 * same time whether or not the address belongs to an account — a synchronous
 * send makes "account exists" measurable from the response time alone.
 *
 * ⚠️ **THE CLAIM THAT USED TO BE HERE WAS FALSE, AND QUEUEING IS WHY** (704).
 * It read: *"the plaintext token exists in exactly two places: this message, and
 * the browser of whoever clicks it. It is never stored, never logged."* The
 * first sentence stands. The second does not, and did not on the day it was
 * written: this notification implements `ShouldQueue`, production runs
 * `QUEUE_CONNECTION=database` (415), and a queued notification is **serialised
 * whole into `jobs.payload`** — constructor arguments included, which here is
 * the sign-in URL with the token in it. If delivery fails three times the same
 * payload is copied into `failed_jobs`.
 *
 * ⚠️ **THIS SAID "`failed_jobs`, WHICH NOTHING PRUNES" AND THAT STOPPED BEING
 * TRUE ON 2026-08-23 (8610-8639).** `jobs:prune-failed` gives that table a
 * thirty-day horizon, so the copy is bounded rather than permanent. ⛔ **It
 * changes nothing about this paragraph's conclusion and must not be read as
 * closing it**: thirty days is two thousand times the token's own fifteen-minute
 * life, the row is still reachable by anybody who can read the database, and a
 * horizon is not an erasure path. What makes this safe is still the expiry
 * below, not the sweep.
 *
 * It is recorded rather than fixed, because the honest mitigation is already in
 * place and is stronger than the sentence it replaces: **the token is dead
 * fifteen minutes after it is minted**, so a retained payload carries an expired
 * secret, and single-use consumption means even a live one is spent by its
 * owner. Removing the exposure means not queueing, and not queueing reopens the
 * timing oracle the paragraph above exists to close.
 *
 * What remains true without qualification: the token is never *logged*, and
 * never returned by the endpoint that creates it.
 *
 * Outcome language, no jargon: the owner is told what to do, not what a token
 * is.
 */
final class MagicLinkLogin extends Notification implements ClassifiesUnderCanSpam, ShouldQueue
{
    use Queueable;

    /**
     * §7702(17)(A)(ii) — it facilitates a transaction the recipient asked for,
     * two minutes ago, by typing their address into the sign-in form.
     *
     * ⛔ **AN UNSUBSCRIBE LINK HERE WOULD BE A LOCK-OUT MECHANISM.** The
     * opt-out this application honours is real: it writes a suppression that
     * refuses future sends. On the one message that is the recipient's only way
     * back into their own account, offering it — and honouring it — is a
     * support ticket that cannot be resolved by mail.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    public function __construct(
        private readonly string $url,
        private readonly int $minutes,
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
            ->subject('Your sign-in link')
            ->greeting('Here is your sign-in link')
            ->line('Use the button below to sign in. No password needed.')
            ->action('Sign in', $this->url)
            ->line("The link works once and expires in {$this->minutes} minutes.")
            ->line('If you did not ask to sign in, you can ignore this email — '
                .'nobody can get in without the link.');
    }
}
