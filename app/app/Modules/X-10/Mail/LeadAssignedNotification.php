<?php

declare(strict_types=1);

namespace App\Modules\X10\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadAssignedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly int $businessId,
        public readonly int $leadId,
        public readonly string $leadName,
        public readonly ?string $leadPhone,
        public readonly ?string $leadEmail,
        public readonly string $reason
    ) {}

    public function build(): self
    {
        return $this->subject('New lead assigned to you: '.$this->leadName)
            ->html('<p>A new lead was assigned to you.</p><p>Name: '.e($this->leadName).'</p><p>Phone: '.e((string) $this->leadPhone).'</p><p>Email: '.e((string) $this->leadEmail).'</p><p>Why you: '.e($this->reason).'</p>');
    }
}
