<?php

declare(strict_types=1);

namespace App\Modules\X127\Actions;

use App\Modules\X127\Models\TenantZeroConfig;

final class TenantzeroConfigAction
{
    public function handle(int $businessId, bool $isTenantZero = true, bool $publicProof = true): TenantZeroConfig
    {
        return TenantZeroConfig::updateOrCreate(
            ['business_id' => $businessId],
            [
                'is_tenant_zero' => $isTenantZero,
                'public_proof_enabled' => $publicProof,
            ]
        );
    }
}
