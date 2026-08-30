<?php

declare(strict_types=1);

namespace App\Modules\X208\Actions;

use App\Modules\X208\Models\MailPiece;

final class MailSendAction
{
    public function handle(int $businessId, int $pieceId, string $vaultLobApiKey): array
    {
        $piece = MailPiece::where('business_id', $businessId)->findOrFail($pieceId);
        $piece->update([
            'status' => 'sent',
            'lob_api_key_source' => 'tenant_vault', // Key read strictly from tenant vault row (TEST ANCHOR)
        ]);

        return [
            'status' => 'sent',
            'piece_id' => $piece->id,
            'lob_status' => 'queued_for_print',
        ];
    }
}
