<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The guaranteed-visible artifact — Day 5, sent only when nothing has won by
 * then (`28` §3.2).
 *
 * ⚠️ **NARROWER THAN §3.2's TWO ALTERNATIVES, AND BOTH ARE MISSING FOR THE SAME
 * REASON: NOTHING WRITES THEM YET.** §3.2 offers "a before/after site-fix
 * summary" — no table anywhere records what a site fix changed and reverted
 * (`speed_change_sets` is `28` Part 12's own table for that, and it is unbuilt)
 * — or "your review link + QR sign is ready, with the PDF" — there is no QR
 * generator and no PDF renderer in this application. What genuinely exists and
 * has had a real writer since signup is the feedback page itself, provisioned
 * for every tenant at `TenantProvisioner::provision()` time. This sends that
 * link. Building either of §3.2's two named alternatives is a separate slice,
 * not a corner of this one.
 */
final class FirstWeekFallbackProof extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * §7702(17)(A)(v) — it hands over a working review link the account now has.
     * Delivering a feature to the person who is paying for it is the
     * relationship, not a solicitation.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    public function __construct(private readonly string $reviewLinkUrl) {}

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
            ->subject('Your review link is ready')
            ->greeting('Something you can use right away')
            ->line('No review has come in yet, so here is something you can act on today: your own review link.')
            ->action('Your review link', $this->reviewLinkUrl)
            ->line('Share it however suits you — on a receipt, a text, or a sign at the counter.');
    }
}
