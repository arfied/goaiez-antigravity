<?php

declare(strict_types=1);

namespace App\Modules\X219\Actions;

use App\Modules\X219\Models\AiModel;
use App\Modules\X219\Models\AiModuleAssignment;

final class ModelResolveAction
{
    public function handle(int $businessId, string $targetModule, bool $isComplex = false): ?string
    {
        $assignment = AiModuleAssignment::where('business_id', $businessId)
            ->where('target_module', $targetModule)
            ->first();

        if ($assignment === null) {
            return null;
        }

        $modelId = $isComplex && $assignment->complex_model_id
            ? $assignment->complex_model_id
            : $assignment->primary_model_id;

        $model = AiModel::find($modelId);

        return $model ? $model->model_name : null;
    }
}
