<?php

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\Deployment;

final class LatestDeploymentForPageAction
{
    /**
     * The most recent deployment of one page, or null. Same-module read: X-103 must not import X-157's model (BoundaryStage:87-93).
     * the control arm is the page's deployment; a variant arm is never the owner's "live at" link.
     */
    public function handle(int $businessId, int $pageId): ?Deployment
    {
        return Deployment::where('business_id', $businessId)->where('page_id', $pageId)->whereNull('page_variant_id')->latest('id')->first();
    }
}
