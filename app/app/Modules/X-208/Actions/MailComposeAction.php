<?php

declare(strict_types=1);

namespace App\Modules\X208\Actions;

use App\Modules\X208\Models\MailPiece;

final class MailComposeAction
{
    /**
     * Compose mail piece. DNM addresses produce NO PIECE and are refused at generation (TEST ANCHOR).
     */
    public function handle(
        int $businessId,
        string $recipientAddress,
        bool $isDoNotMail = false,
        string $format = '4x6'
    ): array {
        // 1. Do-Not-Mail check: produces NO PIECE (TEST ANCHOR)
        if ($isDoNotMail) {
            return [
                'status' => 'refused',
                'refusal_code' => 'RECIPIENT_ON_DO_NOT_MAIL_LIST',
                'message' => 'Do-Not-Mail address produces NO PIECE — refused at generation',
                'piece' => null,
            ];
        }

        $piece = MailPiece::create([
            'business_id' => $businessId,
            'recipient_address' => $recipientAddress,
            'postcard_format' => $format,
            'cost_cents' => 72,
            'status' => 'composed',
            'lob_api_key_source' => 'tenant_vault',
        ]);

        return [
            'status' => 'composed',
            'piece_id' => $piece->id,
            'cost_cents' => 72,
            'piece' => $piece,
        ];
    }
}
