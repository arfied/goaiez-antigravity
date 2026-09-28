<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Actions;

use App\Exceptions\ZernioConnectionRefused;
use App\Models\ZernioAccountBinding;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Services\Zernio\ZernioWhatsappClient;
use Illuminate\Support\Facades\DB;

final class WhatsappConnectCompleteAction
{
    public function __construct(
        private readonly ZernioWhatsappClient $whatsappClient,
    ) {}

    public function handle(string $accountRef, string $expectedProfileRef, ?string $profileRef, ?string $label, string $actor): WhatsappConnection
    {
        $row = WhatsappConnection::first();

        if ($row === null || $row->status !== 'pending') {
            throw ZernioConnectionRefused::notStarted();
        }

        if ($expectedProfileRef !== $row->provider_profile_ref || ($profileRef !== null && $profileRef !== $expectedProfileRef)) {
            throw ZernioConnectionRefused::wrongProfile();
        }

        if (! $this->whatsappClient->profileOwnsAccount($expectedProfileRef, $accountRef)) {
            throw ZernioConnectionRefused::accountNotOnProfile('WhatsApp');
        }

        return DB::transaction(function () use ($row, $accountRef, $expectedProfileRef, $label): WhatsappConnection {
            $existingBinding = ZernioAccountBinding::where('account_ref', $accountRef)->first();

            if ($existingBinding !== null && $existingBinding->profile_ref !== $expectedProfileRef) {
                throw ZernioConnectionRefused::wrongProfile();
            }

            $row->account_ref = $accountRef;
            $row->display_label = $label;
            $row->status = 'connected';
            $row->connected_at = now();
            $row->last_error = null;
            $row->save();

            ZernioAccountBinding::firstOrCreate(
                ['account_ref' => $accountRef],
                ['profile_ref' => $expectedProfileRef, 'platform' => 'whatsapp']
            );

            return $row;
        });
    }
}
