<?php

declare(strict_types=1);

namespace App\Modules\X186\Events;

final class SendRequested
{
    /**
     * TEST ANCHOR: every send.requested from this module carries messageClass = 'marketing' (P-063, G11-24)
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $personId,
        public readonly string $channel,
        public readonly string $messageClass = 'marketing',
        public readonly string $templateName = 'default_drip'
    ) {}
}
