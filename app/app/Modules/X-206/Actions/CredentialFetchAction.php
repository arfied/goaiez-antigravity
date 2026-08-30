<?php

declare(strict_types=1);

namespace App\Modules\X206\Actions;

use App\Modules\X206\Models\Credential;
use Illuminate\Support\Facades\Crypt;

final class CredentialFetchAction
{
    public function handle(int $businessId, string $serviceName): ?string
    {
        $cred = Credential::where('business_id', $businessId)
            ->where('service_name', $serviceName)
            ->first();

        if ($cred === null) {
            return null;
        }

        return Crypt::decryptString($cred->encrypted_secret);
    }
}
