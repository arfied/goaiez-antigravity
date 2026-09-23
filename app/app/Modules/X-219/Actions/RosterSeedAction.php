<?php

declare(strict_types=1);

namespace App\Modules\X219\Actions;

use App\Enums\AiModel as AiModelEnum;
use App\Enums\AiProvider as AiProviderEnum;
use App\Modules\X219\Models\AiModel;
use App\Modules\X219\Models\AiProvider;
use Illuminate\Support\Facades\DB;

final class RosterSeedAction
{
    public function handle(int $businessId): array
    {
        return DB::transaction(function () use ($businessId) {
            $providers = [];
            foreach (AiProviderEnum::cases() as $providerEnum) {
                $providers[$providerEnum->name] = AiProvider::firstOrCreate([
                    'business_id' => $businessId,
                    'provider_name' => $providerEnum->value,
                ]);
            }

            $models = [];
            foreach (AiModelEnum::cases() as $modelEnum) {
                $providerModel = $providers[$modelEnum->provider()->name];

                $models[] = AiModel::firstOrCreate(
                    [
                        'business_id' => $businessId,
                        'model_name' => $modelEnum->value,
                    ],
                    [
                        'provider_id' => $providerModel->id,
                        'cost_per_1k_input_cents' => $modelEnum->inputPricePerMillion(),
                        'cost_per_1k_output_cents' => $modelEnum->outputPricePerMillion(),
                        'capabilities' => ['embedding' => $modelEnum->isEmbedding()],
                    ]
                );
            }

            return [
                'providers' => count($providers),
                'models' => count($models),
            ];
        });
    }
}
