<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Domain;

final class ReconciliationEngine
{
    /**
     * [G1-10] an unreconciled cent RAISES, asserted by injecting a one-cent difference
     */
    public function reconcile(int $ledgerTotalCents, int $gatewayTotalCents): void
    {
        if ($ledgerTotalCents !== $gatewayTotalCents) {
            throw new \RuntimeException('Unreconciled difference detected: ' . ($gatewayTotalCents - $ledgerTotalCents) . ' cents');
        }
    }
}
