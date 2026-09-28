<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EstimateEmail extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    public function __construct(
        private readonly string $businessName,
        private readonly string $estimateNumber,
        private readonly array $lines,
        private readonly int $totalCents,
        private readonly ?string $expiresOn,
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
        $mail = (new MailMessage)
            ->subject("Your estimate {$this->estimateNumber} from {$this->businessName}");

        foreach ($this->lines as $line) {
            $formattedSubtotal = number_format($line['subtotal_cents'] / 100, 2);
            $mail->line("{$line['quantity']} × {$line['service_name']}: \${$formattedSubtotal}");
        }

        $formattedTotal = number_format($this->totalCents / 100, 2);
        $mail->line("Total: \${$formattedTotal}");

        if ($this->expiresOn !== null) {
            $mail->line("This estimate is valid until {$this->expiresOn}.");
        }

        $mail->line('Reply to this email with any questions.');

        return $mail;
    }
}
