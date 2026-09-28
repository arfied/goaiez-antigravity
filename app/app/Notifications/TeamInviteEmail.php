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
 * The email that lets an invited teammate set their password and join a business.
 */
final class TeamInviteEmail extends Notification implements ClassifiesUnderCanSpam, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $businessName,
        private readonly string $url,
        private readonly int $minutes,
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
            ->subject("You've been invited to {$this->businessName}")
            ->line("{$this->businessName} added you to their team on GO AI EZ.")
            ->action('Set your password', $this->url)
            ->line("This link expires in {$this->minutes} minutes.")
            ->line('If you weren\'t expecting this, you can ignore this email.');
    }
}
