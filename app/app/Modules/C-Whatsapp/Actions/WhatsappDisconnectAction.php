<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Actions;

use App\Exceptions\GbpRequestFailed;
use App\Models\ZernioAccountBinding;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Services\Zernio\ZernioWhatsappClient;

final class WhatsappDisconnectAction
{
    public function __construct(
        private readonly ZernioWhatsappClient $whatsappClient,
    ) {}

    public function handle(string $actor): void
    {
        $row = WhatsappConnection::where('status', 'connected')->first();

        if ($row === null) {
            return;
        }

        try {
            $this->whatsappClient->disconnectAccount($row->account_ref);

            ZernioAccountBinding::where('account_ref', $row->account_ref)->delete();

            $row->status = 'disconnected';
            $row->disconnected_at = now();
            $row->last_error = null;
            $row->save();
        } catch (GbpRequestFailed) {
            ZernioAccountBinding::where('account_ref', $row->account_ref)->update([
                'revocation_owed_at' => now(),
            ]);

            $row->status = 'disconnected';
            $row->disconnected_at = now();
            // TODO(Q-045): nothing retries it yet
            $row->last_error = 'Zernio did not confirm the disconnect; we will retry.';
            $row->save();
        }
    }
}
