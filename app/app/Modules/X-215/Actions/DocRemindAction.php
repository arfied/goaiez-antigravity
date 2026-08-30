<?php

declare(strict_types=1);

namespace App\Modules\X215\Actions;

use App\Modules\X215\Models\SignatureRequest;

final class DocRemindAction
{
    public function handle(int $businessId, int $requestId): array
    {
        $request = SignatureRequest::where('business_id', $businessId)->findOrFail($requestId);

        return [
            'status' => 'reminder_sent',
            'signer_email' => $request->signer_email,
        ];
    }
}
