<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * The one message AUTO-WITH-HOLD sends (`29` §4.5).
 *
 * *"Each sends one SMS: 'Replying to a 2★ review from John in 2 hours. Reply
 * STOP to hold, or 3 to read it.'"*
 *
 * ⚠️ **IT IS AN EMAIL AND THE SPEC SAYS SMS, AND THE SUBSTITUTION IS RECORDED
 * RATHER THAN SLID PAST** (`BUILD-PLAN` §2.11.6 row 5). Outbound SMS transmits
 * nothing on this deployment — `SMS_DRIVER` selects the log texter and
 * `review_invite.sms_enabled` is off — so a notice sent by SMS would be a hold
 * window opened on a message nobody could receive, which is exactly the
 * fail-open this slice is built to refuse. The channel moves when that clock
 * clears; **what does not move is that the hold never opens unless the notice
 * can be delivered.**
 *
 * ⛔ **THE STOP LINK IS THE POINT OF THE MESSAGE.** A notice with no way to say
 * no makes *"proceeds on silence"* a statement about a question that was never
 * asked. The link is `signed` and carries the tenant as a route parameter on
 * `campaign.media`'s pattern — the owner is not necessarily signed in, this
 * arrives on a phone, and row-level security answers a URL naming the wrong
 * tenant.
 *
 * ⚠️ **A LINK SCANNER FETCHING IT HOLDS THE PAGE, AND THAT IS THE SAFE
 * DIRECTION** (5671). Corporate mail filters follow links; an unsubscribe link
 * fetched by a scanner silently opts somebody out, which is why those are
 * two-step. Here the fetched outcome is *"do not publish yet"* — the
 * conservative answer on somebody else's website — and the cost of a false STOP
 * is that a page waits for a person. A confirmation step would buy nothing and
 * cost the owner a tap.
 */
final class GrowthPageHoldOpened extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    public function __construct(
        private readonly string $pageTitle,
        private readonly string $pageUrl,
        private readonly Carbon $publishesAt,
        private readonly int $pageId,
        private readonly int $businessId,
    ) {}

    /**
     * ⚠️ **RELATIONSHIP MAIL, NOT MARKETING.** It is about work this platform is
     * about to do on the recipient's own account, at their request, and it
     * carries a way to stop it.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        unset($notifiable);

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        unset($notifiable);

        return (new MailMessage)
            ->subject('A new page goes on your website tomorrow')
            ->greeting('We wrote a page for your website')
            ->line('"'.$this->pageTitle.'"')
            ->line('It goes live at '.$this->pageUrl.' on '.$this->publishesAt->toDayDateTimeString().'.')
            ->line('You do not have to do anything. If you would rather look at it first, hold it here and it will wait for you.')
            ->action('Hold this page', $this->stopUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        unset($notifiable);

        // ⚠️ **NO PAGE COPY.** The database row is the record of what we wrote.
        return ['growth_page_id' => $this->pageId];
    }

    /**
     * ⚠️ **THE SIGNATURE EXPIRES WITH THE WINDOW, PLUS A DAY.** A stop link that
     * outlived its hold would be a permanently valid URL for holding a page that
     * has been live for a month; one that expired exactly on the hour would be
     * dead for an owner reading the mail as it published.
     */
    private function stopUrl(): string
    {
        return URL::temporarySignedRoute(
            'content.growth-page.hold',
            $this->publishesAt->copy()->addDay(),
            ['business' => $this->businessId, 'page' => $this->pageId],
        );
    }
}
