<?php

declare(strict_types=1);

namespace App\Modules\X136\Actions;

use App\Modules\X136\Models\DecayModel;
use App\Services\Config\DefaultsRegistry;

final class DecayModelEnsureAction
{
    public function handle(int $businessId, string $signalType): DecayModel
    {
        $registry = app(DefaultsRegistry::class);

        return DecayModel::firstOrCreate(
            [
                'business_id' => $businessId,
                'signal_type' => $signalType,
            ],
            [
                'half_life_days' => $registry->int('signals.decay.half_life_days'),
                'decay_rate' => $registry->float('signals.decay.rate_pct') / 100,
            ]
        );
    }
}
