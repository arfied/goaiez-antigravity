<?php

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\Deployment;

final class LatestDeploymentForPageAction
{
    /** The most recent deployment of one page, or null. Same-module read: X-103 must not import X-157's model (BoundaryStage:87-93). */
    public function handle(int $businessId, int $pageId): ?Deployment
    {
        return Deployment::where('business_id', $businessId)->where('page_id', $pageId)->latest('id')->first();
    }
}
