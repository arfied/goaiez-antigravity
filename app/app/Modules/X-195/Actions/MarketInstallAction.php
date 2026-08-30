<?php

declare(strict_types=1);

namespace App\Modules\X195\Actions;

use App\Modules\X195\Events\ManifestInstalled;
use App\Modules\X195\Models\Install;
use App\Modules\X195\Models\MarketItem;
use Illuminate\Support\Facades\Event;

final class MarketInstallAction
{
    /**
     * Installs a marketplace item into tenant workspace.
     * 1. Writes rows to installs and config tables, and ZERO files under app/ (TEST ANCHOR).
     * 2. Increments install count (G9-07).
     */
    public function install(
        int $businessId,
        int $marketItemId,
        ?array $configValues = null
    ): Install {
        $marketItem = MarketItem::where('business_id', $businessId)->findOrFail($marketItemId);

        // Record install row only (G2-50: manifest + config rows, never executable files)
        $install = Install::create([
            'business_id' => $businessId,
            'market_item_id' => $marketItem->id,
            'installed_version' => $marketItem->version,
            'config_values' => $configValues ?? [],
            'status' => 'active',
        ]);

        // Increment install count (G9-07)
        $marketItem->increment('install_count');

        Event::dispatch(new ManifestInstalled($businessId, $install->id, $marketItem->id, $marketItem->version));

        return $install;
    }
}
