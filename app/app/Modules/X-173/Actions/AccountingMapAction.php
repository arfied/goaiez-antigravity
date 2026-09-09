<?php

declare(strict_types=1);

namespace App\Modules\X173\Actions;

use App\Modules\X173\Domain\AccountingSyncEngine;

final class AccountingMapAction
{
    public function mapAccount(
        int $businessId,
        int $connectionId,
        string $internalCategory,
        string $remoteGlAccountId,
        string $remoteGlAccountName
    ): array {
        return app(AccountingSyncEngine::class)->mapAccount($businessId, $connectionId, $internalCategory, $remoteGlAccountId, $remoteGlAccountName);
    }
}
