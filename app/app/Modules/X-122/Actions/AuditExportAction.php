<?php

declare(strict_types=1);

namespace App\Modules\X122\Actions;

use App\Modules\X122\Models\ActionInvocation;

final class AuditExportAction
{
    /**
     * Export immutable audit log signed with per-tenant HMAC-SHA256 (G10-35, G10-41).
     */
    public function handle(int $businessId, string $tenantSecret): array
    {
        $invocations = ActionInvocation::where('business_id', $businessId)
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        $serialized = json_encode($invocations);
        $signature = hash_hmac('sha256', $serialized ?: '', $tenantSecret);

        return [
            'business_id' => $businessId,
            'records_count' => count($invocations),
            'records' => $invocations,
            'hmac_sha256' => $signature,
            'algorithm' => 'HMAC-SHA256',
        ];
    }
}
