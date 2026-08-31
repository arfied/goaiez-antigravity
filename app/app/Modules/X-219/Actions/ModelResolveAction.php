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
    public function handle(int $businessId, string $targetModule, bool $isComplex = false): array
    {
        $assignment = AiModuleAssignment::where('business_id', $businessId)
            ->where('target_module', $targetModule)
            ->first();

        if ($assignment === null) {
            // Default spine fallback
            return [
                'model' => 'default_primary',
                'provider' => 'default_provider',
                'is_fallback' => false,
            ];
        }

        $modelId = $isComplex && $assignment->complex_model_id
            ? $assignment->complex_model_id
            : $assignment->primary_model_id;

        $primary = AiModel::find($modelId);
        $primaryProvider = $primary ? AiProvider::find($primary->provider_id) : null;

        // If primary provider is down or degraded, fallback to backup model (R237: different vendor)
        if ($primaryProvider === null || $primaryProvider->status !== 'healthy') {
            $backup = AiModel::find($assignment->backup_model_id);
            $backupProvider = $backup ? AiProvider::find($backup->provider_id) : null;

            Event::dispatch(new ModelFallback(
                businessId: $businessId,
                targetModule: $targetModule,
                primaryModel: $primary ? $primary->model_name : 'unknown',
                fallbackModel: $backup ? $backup->model_name : 'default_backup',
                reason: "Primary provider status: {$primaryProvider?->status}"
            ));

            return [
                'model' => $backup ? $backup->model_name : 'default_backup',
                'provider' => $backupProvider ? $backupProvider->provider_name : 'default_provider',
                'is_fallback' => true,
                'fallback_reason' => "Primary provider status: {$primaryProvider?->status}",
            ];
        }

        Event::dispatch(new ModelResolved(
            businessId: $businessId,
            targetModule: $targetModule,
            modelName: $primary->model_name,
            isFallback: false
        ));

        return [
            'model' => $primary->model_name,
            'provider' => $primaryProvider->provider_name,
            'is_fallback' => false,
        ];
    }
}
