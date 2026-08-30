<?php

declare(strict_types=1);

namespace App\Modules\X206\Actions;

use App\Modules\X206\Events\CredentialStored;
use App\Modules\X206\Models\Credential;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;

final class CredentialStoreAction
{
    public function handle(
        int $businessId,
        string $serviceName,
        string $secret,
        ?string $hint = null,
        ?string $expiresAt = null
    ): Credential {
        $encrypted = Crypt::encryptString($secret);
        $keyHint = $hint ?? (strlen($secret) > 4 ? '...'.substr($secret, -4) : '****');

        $cred = Credential::updateOrCreate(
            ['business_id' => $businessId, 'service_name' => $serviceName],
            [
                'encrypted_secret' => $encrypted,
                'key_hint' => $keyHint,
                'expires_at' => $expiresAt ? now()->parse($expiresAt) : null,
            ]
        );

        Event::dispatch(new CredentialStored(
            businessId: $businessId,
            credentialId: $cred->id,
            serviceName: $serviceName
        ));

        return $cred;
    }
}
