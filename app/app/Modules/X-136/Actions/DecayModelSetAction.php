<?php

declare(strict_types=1);

namespace App\Modules\X136\Actions;

use App\Modules\X136\Models\DecayModel;

final class DecayModelSetAction
{
    /**
     * @return DecayModel|array{refused: string}
     */
    public function handle(int $businessId, string $signalType, int $halfLifeDays, float $decayRate): DecayModel|array
    {
        if ($halfLifeDays < 1 || $halfLifeDays > 365) {
            return ['refused' => 'Half life must be between 1 and 365 days.'];
        }

        if ($decayRate < 0.001 || $decayRate > 0.999) {
            return ['refused' => 'Decay rate must be between 0.001 and 0.999.'];
        }

        $model = DecayModel::firstOrCreate(
            [
                'business_id' => $businessId,
                'signal_type' => $signalType,
            ],
            [
                'half_life_days' => $halfLifeDays,
                'decay_rate' => $decayRate,
            ]
        );

        if (! $model->wasRecentlyCreated) {
            $model->update([
                'half_life_days' => $halfLifeDays,
                'decay_rate' => $decayRate,
            ]);
        }

        return $model;
    }
}
