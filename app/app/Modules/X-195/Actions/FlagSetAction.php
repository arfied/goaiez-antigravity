<?php

declare(strict_types=1);

namespace App\Modules\X195\Actions;

use App\Modules\X195\Events\FlagChanged;
use App\Modules\X195\Models\FeatureFlag;
use Illuminate\Support\Facades\Event;

final class FlagSetAction
{
    /**
     * Sets feature flag for blast-radius control (G4-13, G19-05).
     */
    public function setFlag(
        int $businessId,
        string $flagKey,
        bool $isEnabled,
        int $blastRadiusPct = 100
    ): FeatureFlag {
        $flag = FeatureFlag::updateOrCreate(
            ['business_id' => $businessId, 'flag_key' => $flagKey],
            [
                'is_enabled' => $isEnabled,
                'blast_radius_pct' => $blastRadiusPct,
            ]
        );

        Event::dispatch(new FlagChanged($businessId, $flagKey, $isEnabled, $blastRadiusPct));

        return $flag;
    }
}
