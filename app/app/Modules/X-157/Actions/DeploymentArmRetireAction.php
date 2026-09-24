<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\Deployment;

class DeploymentArmRetireAction
{
    public function retire(int $businessId, string $deployHash): void
    {
        Deployment::where('business_id', $businessId)
            ->where('deploy_hash', $deployHash)
            ->update(['status' => 'superseded']);
    }
}
