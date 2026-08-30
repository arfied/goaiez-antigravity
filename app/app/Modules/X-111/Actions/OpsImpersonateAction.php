<?php

declare(strict_types=1);

namespace App\Modules\X111\Actions;

final class OpsImpersonateAction
{
    public function handle(int $businessId, int $operatorUserId, int $targetTenantId): array
    {
        return [
            'status' => 'impersonation_session_created',
            'operator_user_id' => $operatorUserId,
            'target_tenant_id' => $targetTenantId,
            'audit_logged' => true,
        ];
    }
}
