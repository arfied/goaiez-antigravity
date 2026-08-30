<?php

declare(strict_types=1);

namespace App\Modules\X104\Actions;

use App\Modules\X104\Models\PluginInstall;

final class PluginDeactivateAction
{
    /**
     * Deactivation removes EVERY injected asset (TEST ANCHOR).
     */
    public function deactivate(int $businessId, string $siteUrl): PluginInstall
    {
        $install = PluginInstall::where('business_id', $businessId)->where('site_url', $siteUrl)->firstOrFail();

        $install->update([
            'is_active' => false,
            'injected_assets' => [], // Injected assets completely removed (TEST ANCHOR)
            'pillars_active' => [
                'chat' => false,
                'pixel' => false,
                'reviews_badge' => false,
                'form_hijack' => false,
            ],
        ]);

        return $install;
    }
}
