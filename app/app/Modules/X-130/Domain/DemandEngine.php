<?php

namespace App\Modules\X130\Domain;

class DemandEngine
{
    public function query($tenantCount)
    {
        if ($tenantCount < 5) {
            return ['status' => 'refused', 'reason' => 'below_n'];
        }

        return ['status' => 'aggregate_only'];
    }
}
