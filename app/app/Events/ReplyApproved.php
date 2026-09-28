<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReplyApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $businessId,
        public int $replyId,
        public int $reviewId,
        public string $reviewText,
        public string $approvedText,
        public ?string $promptId = null,
        public ?string $promptVersion = null,
    ) {}
}
