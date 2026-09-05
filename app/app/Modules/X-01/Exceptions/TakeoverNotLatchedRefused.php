<?php

declare(strict_types=1);

namespace App\Modules\X01\Exceptions;

use RuntimeException;

final class TakeoverNotLatchedRefused extends RuntimeException
{
    public const REFUSAL_CODE = 'TAKEOVER_NOT_LATCHED';

    public static function forConversation(int $conversationId): self
    {
        return new self("Takeover not latched for conversation: {$conversationId}");
    }
}
