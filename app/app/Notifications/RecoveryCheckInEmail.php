<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Did we get that sorted?" — T546 §37.3(1), wave 38 lane C (10590–10609).
 *
 * ⚠️ **THIS IS NOT A REVIEW INVITE AND MUST NEVER BECOME ONE.** It asks
 * exactly one question — whether the thing the customer complained about was
 * fixed — and carries no mention of a public review, a rating, or any
 * destination. The review ask, if one follows, is the *existing*
 * {@see ReviewInviteEmail} sent later through the *existing* review-invite
 * pipeline once `ReviewRouter::reoffer()` (named in prose rather than with
 * `{@see}` — Pint's `fully_qualified_strict_types` turns one into a real
 * `use`, and a notification importing a service is a dependency nobody
 * chose) has recomputed the offer on the strength of the customer's own
 * answer — never this message doing double duty.
 *
 * ⚠️ **TRANSACTIONAL OR RELATIONSHIP UNDER CAN-SPAM, AND THE ARGUMENT DIFFERS
 * FROM {@see ReviewInviteEmail}'s ON PURPOSE.** That message is Commercial
 * because its primary purpose is promotion — asking a stranger to publish a
 * public endorsement. This one's primary purpose is a service-recovery status
 * check on a complaint this platform already has on file; it promotes
 * nothing and offers nothing. §7702(17) is the honest class, which is also
 * why this carries no unsubscribe link and no postal address footer — a
 * customer who wants to stop hearing from this business has STOP on the SMS
 * side and, on the email side, the same suppression register `permit()`
 * already consults for every send.
 *
 * ⚠️ **THE LINK IS A LARAVEL SIGNED URL, NEVER A MINTED SHORT LINK.**
 * `ShortLinks` exists to redirect a click to somewhere else and record it as a
 * `ReviewInvite`/`FeedbackPage`/tenant-page fetch — none of which this is. The
 * signature itself is the whole authorisation (`campaign.media`,
 * `content.growth-page.hold` are the precedent this class follows verbatim):
 * unguessable, tamper-evident, self-expiring, and the tenant travels in the
 * URL because `triage_conversations` is FORCE row-level secured and there is
 * nothing else on an unauthenticated request to resolve one from.
 */
final class RecoveryCheckInEmail extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    public function __construct(
        private readonly string $businessName,
        private readonly string $checkInUrl,
    ) {}

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
            ->subject("Did {$this->businessName} get that sorted for you?")
            ->greeting('Checking in')
            ->line(
                "A little while ago you told {$this->businessName} about a problem, "
                .'and they told us they believe it is fixed.'
            )
            ->line('Did they get it sorted for you?')
            ->action('Let them know', $this->checkInUrl)
            ->line('If you would rather not say, that is completely fine too — no reply is needed.');
    }
}
