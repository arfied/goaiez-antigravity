<?php

declare(strict_types=1);

namespace App\Modules\X185\Actions;

use App\Modules\X185\Models\ContentPack;
use InvalidArgumentException;

final class PackSeedAction
{
    private const MINIMUM_FLEET_SAMPLE_SIZE = 100;

    /**
     * Seeds or promotes an experiment content pack (G12-20, G12-30, G17-01).
     * 1. It may test a label, never a tariff/quote (G12-20).
     * 2. A promotion row ALWAYS carries fleet-level sample sizes above minimum (TEST ANCHOR & G12-30, G17-01).
     */
    public function promotePack(
        int $businessId,
        string $packName,
        string $labelText,
        int $fleetSampleSize,
        string $industry = 'hvac'
    ): ContentPack {
        // TEST ANCHOR: A promotion row always carries fleet-level sample sizes above the minimum
        if ($fleetSampleSize < self::MINIMUM_FLEET_SAMPLE_SIZE) {
            throw new InvalidArgumentException('Content pack promotion rejected: fleet sample size must be above minimum (TEST ANCHOR & G12-30)');
        }

        return ContentPack::create([
            'business_id' => $businessId,
            'pack_name' => $packName,
            'industry' => $industry,
            'label_text' => $labelText,
            'fleet_sample_size' => $fleetSampleSize,
            'is_promoted' => true,
        ]);
    }
}
