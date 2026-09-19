<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

final class AccountManagerChurnNotificationAction
{
    /**
     * [G19-21]
     */
    public function notifyRisk(string $clientId, string $accountManagerId): array
    {
        // R245: Seam for account manager churn notifications.
        return [
            'notified' => $accountManagerId,
            'client_id' => $clientId,
            'reason' => 'churn_risk',
        ];
    }
}
