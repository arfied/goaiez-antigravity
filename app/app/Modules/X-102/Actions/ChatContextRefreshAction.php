<?php

declare(strict_types=1);

namespace App\Modules\X102\Actions;

use App\Modules\X102\Models\ChatSession;
use App\Modules\X110\Domain\PixelEngine;

final class ChatContextRefreshAction
{
    public function __construct(private PixelEngine $pixelEngine) {}

    public function handle(ChatSession $session): void
    {
        if (! $session->pixel_session_token) {
            return;
        }

        $context = $this->pixelEngine->pageContextForSession(
            $session->business_id,
            $session->pixel_session_token
        );

        if ($context) {
            $session->update(['page_context' => $context]);
        }
    }
}
