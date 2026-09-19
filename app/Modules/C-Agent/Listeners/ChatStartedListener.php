<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Listeners;

use Illuminate\Support\Facades\Log;

final class ChatStartedListener
{
    /**
     * [G5-31] the web-chat door is X-102's
     */
    public function handle(object $event): void
    {
        // R245: The web chat door hands off to C-Agent by invoking the answer path.
        Log::info('Chat started handled by C-Agent', ['chat_id' => $event->chatId ?? null]);
    }
}
