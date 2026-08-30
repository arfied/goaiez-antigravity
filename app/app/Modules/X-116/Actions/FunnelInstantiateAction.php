<?php

declare(strict_types=1);

namespace App\Modules\X116\Actions;

use App\Modules\X116\Models\FunnelShape;

final class FunnelInstantiateAction
{
    public function instantiateShape(int $businessId, string $shapeKey, array $stepsFlow): FunnelShape
    {
        return FunnelShape::create([
            'business_id' => $businessId,
            'shape_key' => $shapeKey,
            'steps_flow' => $stepsFlow,
        ]);
    }
}
