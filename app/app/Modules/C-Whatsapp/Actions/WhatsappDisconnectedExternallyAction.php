<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Actions;

use App\Models\ZernioAccountBinding;
use App\Modules\CWhatsapp\Models\WhatsappConnection;

final class WhatsappDisconnectedExternallyAction
{
    public function handle(WhatsappConnection $row): void
    {
        if ($row->account_ref !== null) {
            ZernioAccountBinding::where('account_ref', $row->account_ref)->delete();
        }

        $row->status = 'disconnected';
        $row->disconnected_at = now();
        $row->last_error = 'Disconnected at Zernio.';
        $row->save();
    }
}
