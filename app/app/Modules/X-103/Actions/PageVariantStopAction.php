<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\PageVariant;
use App\Modules\X157\Actions\DeploymentArmRetireAction;

class PageVariantStopAction
{
    public function __construct(
        private DeploymentArmRetireAction $deploymentArmRetireAction
    ) {}

    public function handle(int $businessId, int $variantId, bool $freeze = false): array
    {
        $variant = PageVariant::where('business_id', $businessId)->find($variantId);
        if (! $variant) {
            return ['status' => 'not_found'];
        }

        $variant->update([
            'status' => $freeze ? 'frozen' : 'stopped',
            'stopped_at' => now(),
        ]);

        if ($variant->variant_deploy_hash) {
            $this->deploymentArmRetireAction->retire($businessId, $variant->variant_deploy_hash);
        }

        return ['status' => $freeze ? 'frozen' : 'stopped'];
    }
}
