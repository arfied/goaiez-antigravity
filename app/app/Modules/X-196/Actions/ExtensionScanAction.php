<?php

declare(strict_types=1);

namespace App\Modules\X196\Actions;

use App\Modules\X196\Events\ExtensionAborted;
use App\Modules\X196\Events\ExtensionTriggered;
use App\Modules\X196\Models\ExtensionSession;
use Illuminate\Support\Facades\Event;

final class ExtensionScanAction
{
    /**
     * Scans page DOM (G3-05, G12-08).
     * TEST ANCHOR: A simulated rate-limit banner stops all extension activity within one action and writes extension.aborted.
     */
    public function scanPage(
        int $businessId,
        int $sessionId,
        string $pageUrl,
        string $pageDomContent
    ): ExtensionSession {
        $session = ExtensionSession::where('business_id', $businessId)->findOrFail($sessionId);

        // Check for rate limit / bot detection banner in DOM
        if (preg_match('/(rate limit exceeded|too many requests|captcha required|access denied|block-banner)/i', $pageDomContent)) {
            // TEST ANCHOR: Stops all extension activity within one action and writes extension.aborted
            $session->update([
                'is_active' => false,
                'is_aborted' => true,
                'abort_reason' => 'Rate-limit banner detected: all extension activity terminated immediately (TEST ANCHOR)',
            ]);

            Event::dispatch(new ExtensionAborted($businessId, $session->id, $session->abort_reason));

            return $session;
        }

        $session->increment('actions_count');

        Event::dispatch(new ExtensionTriggered($businessId, $session->id, $pageUrl));

        return $session;
    }
}
