<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Content\PageAdvisory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The T4 rung, delivered (`41` Part 1).
 *
 * ⛔ **THIS IS `handoff()` AND `handoff()` IS NOT A DEGRADED APOLOGY**
 * (`CLAUDE.md` rule 44). The owner receives the page that cleared the same
 * quality gate a T1 site's page clears, with the address it belongs at, ready to
 * paste into whatever their site is built with. The tier is weaker; the content
 * is identical, and it is identical **because one implementation produces both**.
 *
 * ⚠️ **IT SAYS PLAINLY THAT NOTHING WAS DONE TO THEIR WEBSITE**, which `41`
 * Part 1 requires of every T4 string and `22` requires of every string: a
 * hand-off described as a publication is a claim about somebody's site that
 * nobody here can support.
 */
final class GrowthPageReadyToPaste extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    public function __construct(private readonly PageAdvisory $advisory) {}

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
            ->subject('A page for your website, ready to paste')
            ->greeting('We wrote a page for your website')
            ->line('We cannot put it up for you, so here it is to paste in yourself. Nothing on your website has changed.')
            ->line($this->advisory->asPasteableText());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        unset($notifiable);

        return ['growth_page_id' => $this->advisory->pageId];
    }
}
