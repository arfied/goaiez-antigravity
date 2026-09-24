<?php

declare(strict_types=1);

namespace App\Modules\X206\Actions;

use App\Modules\X206\Models\Credential;

final class CredentialForgetAction
{
    public function handle(int $businessId, string $serviceName): bool
    {
        return (bool) Credential::where('business_id', $businessId)
            ->where('service_name', $serviceName)
            ->delete();
    }
}
