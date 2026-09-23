<?php

declare(strict_types=1);

namespace App\Modules\X219\Actions;

use App\Modules\X219\Events\ModelFallback;
use App\Modules\X219\Events\ModelResolved;
use App\Modules\X219\Models\AiModel;
use App\Modules\X219\Models\AiModuleAssignment;
use App\Modules\X219\Models\AiProvider;
use Illuminate\Support\Facades\Event;

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

        $primary = AiModel::find($modelId);
        $primaryProvider = $primary ? AiProvider::find($primary->provider_id) : null;

        if ($primaryProvider === null || $primaryProvider->status !== 'healthy') {
            $backup = AiModel::find($assignment->backup_model_id);

            Event::dispatch(new ModelFallback(
                businessId: $businessId,
                targetModule: $targetModule,
                primaryModel: $primary ? $primary->model_name : 'unknown',
                fallbackModel: $backup ? $backup->model_name : 'unknown',
                reason: "Primary provider status: {$primaryProvider?->status}"
            ));

            return $backup ? $backup->model_name : null;
        }

        Event::dispatch(new ModelResolved(
            businessId: $businessId,
            targetModule: $targetModule,
            modelName: $primary->model_name,
            isFallback: false
        ));

        return $primary->model_name;
    }
}
