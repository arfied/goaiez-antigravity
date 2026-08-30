<?php

declare(strict_types=1);

namespace App\Modules\X104\Actions;

use App\Modules\X104\Events\PluginSynced;
use App\Modules\X104\Models\PluginInstall;
use Illuminate\Support\Facades\Event;

final class PluginSyncAction
{
    public function handle(int $businessId, string $siteUrl): array
    {
        $install = PluginInstall::where('business_id', $businessId)->where('site_url', $siteUrl)->firstOrFail();

        Event::dispatch(new PluginSynced($businessId, $siteUrl));

        return [
            'status' => 'synced',
            'site_url' => $siteUrl,
            'is_active' => $install->is_active,
        ];
    }
}
