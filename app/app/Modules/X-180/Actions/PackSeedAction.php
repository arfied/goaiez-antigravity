<?php

declare(strict_types=1);

namespace App\Modules\X180\Actions;

use App\Modules\X180\Events\PackSeeded;
use App\Modules\X180\Models\ContentPack;
use Illuminate\Support\Facades\Event;

final class PackSeedAction
{
    /**
     * Seeds industry content packs.
     * 1. Manifest declared asset count MUST equal rows created (TEST ANCHOR).
     * 2. A packaged asset with no license_source cannot be seeded (TEST ANCHOR).
     */
    public function seed(
        int $businessId,
        string $packName,
        string $industry,
        int $declaredAssetCount,
        array $assets
    ): array {
        // 1. License source verification gate (TEST ANCHOR)
        foreach ($assets as $idx => $asset) {
            $license = $asset['license_source'] ?? null;
            if (empty($license)) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'MISSING_LICENSE_SOURCE',
                    'message' => "Packaged asset at index {$idx} ({$asset['title']}) has no license_source and cannot be seeded",
                    'pack' => null,
                ];
            }
        }

        // 2. Declared asset count MUST equal rows created (TEST ANCHOR)
        $actualCount = count($assets);
        if ($declaredAssetCount !== $actualCount) {
            return [
                'status' => 'refused',
                'refusal_code' => 'ASSET_COUNT_MISMATCH',
                'message' => "Manifest declared count ({$declaredAssetCount}) does not match actual assets ({$actualCount})",
                'pack' => null,
            ];
        }

        $pack = ContentPack::create([
            'business_id' => $businessId,
            'pack_name' => $packName,
            'industry' => $industry,
            'assets_count' => $actualCount,
            'assets_manifest' => $assets,
        ]);

        Event::dispatch(new PackSeeded($businessId, $pack->id, $packName, $actualCount));

        return [
            'status' => 'seeded',
            'pack_id' => $pack->id,
            'pack_name' => $packName,
            'industry' => $industry,
            'assets_created' => $actualCount,
        ];
    }
}
