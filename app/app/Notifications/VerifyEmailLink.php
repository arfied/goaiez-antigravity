<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class VerifyEmailLink extends Notification implements ClassifiesUnderCanSpam, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $url,
    ) {}

    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your email address')
            ->greeting('Verify your email address')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $this->url)
            ->line('If you did not create an account, no further action is required.');
    }
}
