<?php

declare(strict_types=1);

namespace App\Modules\X123\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeadLetterNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly int $businessId,
        public readonly int $eventLogId,
        public readonly string $eventName,
        public readonly string $errorMessage
    ) {}

    public function build(): self
    {
        return $this->subject("DLQ Alert for Business #{$this->businessId}")
            ->html("<p>Event {$this->eventName} (#{$this->eventLogId}) dead-lettered: {$this->errorMessage}</p>");
    }
}
