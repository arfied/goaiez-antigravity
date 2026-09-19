<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Actions;

final class CalculateSavedMrrAction
{
    /**
     * [G9-31] MRR saved by the one dunning ladder (§45A)
     */
    public function handle(array $recoveredSubscriptions): int
    {
        // R245: We calculate the MRR saved by summing the monthly value of subscriptions
        // that were recovered via the dunning ladder.
        $savedMrrCents = 0;
        foreach ($recoveredSubscriptions as $sub) {
            $savedMrrCents += $sub['monthly_price_cents'] ?? 0;
        }

        return $savedMrrCents;
    }
}
