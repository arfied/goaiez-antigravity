<?php

declare(strict_types=1);

namespace App\Modules\X161\Actions;

use App\Modules\X161\Models\DemoLedger;

final class DemoResetAction
{
    public function resetDemo(int $businessId, int $demoTenantId): void
    {
        DemoLedger::where('business_id', $businessId)
            ->where('demo_tenant_id', $demoTenantId)
            ->where('entry_type', 'debit')
            ->delete();
    }
}
