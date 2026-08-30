<?php

declare(strict_types=1);

namespace App\Modules\X173\Actions;

use App\Modules\X173\Models\AccountMapping;

final class AccountingMapAction
{
    public function mapAccount(
        int $businessId,
        int $connectionId,
        string $internalCategory,
        string $remoteGlAccountId,
        string $remoteGlAccountName
    ): AccountMapping {
        return AccountMapping::create([
            'business_id' => $businessId,
            'connection_id' => $connectionId,
            'internal_category' => $internalCategory,
            'remote_gl_account_id' => $remoteGlAccountId,
            'remote_gl_account_name' => $remoteGlAccountName,
        ]);
    }
}
