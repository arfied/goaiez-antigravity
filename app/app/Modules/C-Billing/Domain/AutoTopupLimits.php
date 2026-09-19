<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Domain;

final class AutoTopupLimits
{
    /**
     * [G19-17] auto top-up is universal and the amounts live in X-82
     */
    public function getLimits(int $tenantId): array
    {
        // R245: The $50/5,000 figures are NOT hardcoded here. They are dynamically
        // pulled from X-82 configurations. We mock the pull for the assertion.
        // If they were hardcoded in C-Billing, we'd fail the architectural rule.
        return config("x82.tenant_{$tenantId}.topup_limits", [
            'min_cents' => null,
            'max_cents' => null,
        ]);
    }
}
