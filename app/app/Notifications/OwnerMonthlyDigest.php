<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OwnerMonthlyDigest extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * A report on the account's own activity, at the account holder's
     * request (by having an account) — no offer, no incentive, nothing to
     * buy.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    /**
     * @param  array<int, array{title: string, times: int}>  $pages
     * @param  list<string>  $lines
     */
    public function __construct(
        string $businessName,
        private readonly string $label,
        private readonly array $pages,
        private readonly ?int $visits,
        private readonly array $lines,
        private readonly string $activityUrl,
    ) {
        unset($businessName);
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
        $subject = str_starts_with($this->label, 'since ')
            ? "What your website did {$this->label}"
            : "What your website did — {$this->label}";

        $greetingLabel = str_starts_with($this->label, 'since ')
            ? $this->label
            : "in {$this->label}";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Here is what your website did {$greetingLabel}");

        foreach ($this->pages as $page) {
            $times = $page['times'];
            $title = $page['title'];
            if ($times > 1) {
                $message->line("Published: {$title} ({$times} times)");
            } else {
                $message->line("Published: {$title}");
            }
        }

        if ($this->visits !== null) {
            $message->line("Visits to your site: {$this->visits}");
        } else {
            $message->line('Visits to your site: not measured yet — the page has not sent us a visit');
        }

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        return $message
            ->action('See everything we did', $this->activityUrl)
            ->line('You do not need to do anything — this is just so you can see it working.');
    }
}
