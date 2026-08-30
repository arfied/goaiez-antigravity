<?php

declare(strict_types=1);

namespace App\Modules\X209\Events;

final class FixerActionTaken
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $commandId,
        public readonly string $parsedIntent,
        public readonly ?string $outboundMessageId
    ) {}
}
