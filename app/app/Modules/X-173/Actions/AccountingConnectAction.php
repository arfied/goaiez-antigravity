<?php

declare(strict_types=1);

namespace App\Modules\X173\Actions;

use App\Modules\X173\Models\AccountingConnection;

final class AccountingConnectAction
{
    public function connect(
        int $businessId,
        string $provider,
        ?string $realmId = null,
        ?string $accessToken = null
    ): AccountingConnection {
        return AccountingConnection::create([
            'business_id' => $businessId,
            'provider' => $provider,
            'realm_id' => $realmId,
            'access_token' => $accessToken,
            'is_active' => $accessToken !== null,
        ]);
    }
}
