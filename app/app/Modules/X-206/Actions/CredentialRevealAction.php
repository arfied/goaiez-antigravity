<?php

declare(strict_types=1);

namespace App\Modules\X206\Actions;

use App\Modules\X206\Events\CredentialFailed;
use App\Modules\X206\Events\CredentialRevealed;
use App\Modules\X206\Models\Credential;
use App\Modules\X206\Models\CredentialReveal;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;

final class CredentialRevealAction
{
    /**
     * Reveal a credential. Tenant may reveal owned credential; cross-tenant attempts are refused and logged (TEST ANCHOR).
     */
    public function handle(
        int $businessId,
        int $credentialId,
        ?int $userId = null,
        ?string $ipAddress = '127.0.0.1'
    ): array {
        $cred = Credential::find($credentialId);

        // Cross-tenant or nonexistent check
        if ($cred === null || $cred->business_id !== $businessId) {
            $log = CredentialReveal::create([
                'business_id' => $businessId,
                'credential_id' => $credentialId,
                'user_id' => $userId,
                'service_name' => $cred ? $cred->service_name : 'unknown',
                'ip_address' => $ipAddress ?? '127.0.0.1',
                'status' => 'refused',
                'refusal_reason' => 'UNAUTHORIZED_CROSS_TENANT',
                'revealed_at' => now(),
            ]);

            Event::dispatch(new CredentialFailed(
                businessId: $businessId,
                serviceName: $cred ? $cred->service_name : 'unknown',
                reason: 'UNAUTHORIZED_CROSS_TENANT'
            ));

            return [
                'status' => 'refused',
                'reason' => 'UNAUTHORIZED_CROSS_TENANT',
                'secret' => null,
                'log_id' => $log->id,
            ];
        }

        $decrypted = Crypt::decryptString($cred->encrypted_secret);

        $log = CredentialReveal::create([
            'business_id' => $businessId,
            'credential_id' => $cred->id,
            'user_id' => $userId,
            'service_name' => $cred->service_name,
            'ip_address' => $ipAddress ?? '127.0.0.1',
            'status' => 'permitted',
            'refusal_reason' => null,
            'revealed_at' => now(),
        ]);

        Event::dispatch(new CredentialRevealed(
            businessId: $businessId,
            credentialId: $cred->id,
            serviceName: $cred->service_name,
            userId: $userId
        ));

        return [
            'status' => 'permitted',
            'service_name' => $cred->service_name,
            'secret' => $decrypted,
            'log_id' => $log->id,
        ];
    }
}
