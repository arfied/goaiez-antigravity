<?php

declare(strict_types=1);

namespace App\Modules\X66\Actions;

use App\Modules\X66\Models\CallSession;

final class VoiceTransferAction
{
    public function handle(int $businessId, int $sessionId, string $transferTargetPhone): array
    {
        $session = CallSession::where('business_id', $businessId)->findOrFail($sessionId);

        return [
            'session_id' => $session->id,
            'status' => 'transferred',
            'target' => $transferTargetPhone,
        ];
    }
}
