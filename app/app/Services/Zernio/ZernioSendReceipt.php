<?php

namespace App\Services\Zernio;

final readonly class ZernioSendReceipt
{
    public function __construct(
        public string $outcome,
        public ?string $messageRef = null,
        public ?string $conversationId = null,
        public ?string $code = null,
    ) {}
}
