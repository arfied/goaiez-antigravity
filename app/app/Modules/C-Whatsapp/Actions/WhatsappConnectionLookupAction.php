<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Actions;

use App\Modules\CWhatsapp\Models\WhatsappConnection;

final class WhatsappConnectionLookupAction
{
    public function forAccount(string $accountRef): ?WhatsappConnection
    {
        return WhatsappConnection::where('account_ref', $accountRef)->first();
    }
}
